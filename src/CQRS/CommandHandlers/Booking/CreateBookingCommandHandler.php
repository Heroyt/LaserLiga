<?php

declare(strict_types=1);

namespace App\CQRS\CommandHandlers\Booking;

use App\CQRS\Commands\Booking\CreateBookingCommand;
use App\CQRS\Commands\Google\CreateCalendarEventCommand;
use App\Models\Auth\PersonalDetails;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingSlot;
use App\Models\Booking\BookingUser;
use App\Models\Booking\Enums\LogAction;
use App\Services\Booking\BookingCalendarProvider;
use App\Services\Booking\BookingLogger;
use App\Services\Google\GoogleClientFactory;
use DateTimeImmutable;
use Lsr\CQRS\CommandBus;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Db\DB;
use Lsr\Interfaces\SessionInterface;
use Lsr\Logging\Logger;

final readonly class CreateBookingCommandHandler implements CommandHandlerInterface
{
	public function __construct(
		private BookingCalendarProvider $bookingCalendarProvider,
		private GoogleClientFactory     $googleFactory,
		private CommandBus              $commandBus,
		private BookingLogger           $logger,
		private SessionInterface $session,
	) {
	}

	/**
	 * @param CreateBookingCommand $command
	 */
	public function handle(CommandInterface $command): ?Booking {
		$logger = new Logger(LOG_DIR . 'booking/', 'new-bookings');

		$date = $command->datetime;

		/** @var BookingSlot[] $slots */
		$slots = [];
		foreach ($command->slots as $time => $playerCount) {
			[$hour, $minute] = explode(':', $time);
			$slotDate = $date->setTime((int)$hour, (int)$minute);

			// Convert datetime to valid slot time
			$datetime = $this->bookingCalendarProvider->getSlotTime($slotDate, $command->type, $command->subtype);

			// Check if booking slot is available
			$isAvailable = $this->bookingCalendarProvider->isSlotAvailable(
				$datetime,
				$command->type,
				$command->subtype,
				$playerCount,
				$command->allowAllTimes,
				$command->allowOverbooking,
			);

			if (!$isAvailable) {
				$logger->warning(
					'Failed to create booking: slot is not available',
					[
						'data'        => $command->getLogData(),
						'datetime'    => $datetime,
						'isAvailable' => $isAvailable,
					]
				);
				return null; // Booking slot is not available
			}

			// Convert to immutable DateTime
			if (!$datetime instanceof DateTimeImmutable) {
				$datetime = DateTimeImmutable::createFromInterface($datetime);
			}

			$slot = new BookingSlot();
			$slot->time = $datetime;
			$slot->playerCount = $playerCount;
			$slots[] = $slot;
		}

		DB::begin(); // Start a transaction

		$booking = new Booking();

		// Create users
		foreach ($command->users as $userData) {
			$users = BookingUser::findAllByEmail($userData->email);

			// Assume the user is new
			$user = null;

			// If user already exists, check other detail to make sure the user is the same
			foreach ($users as $u) {
				if (
					($u->user !== null && $u->user->id === $userData->user?->id) // If user is logged in, we can assume the user is the same.
					|| $u->personalDetails->matches( // Checks normalized values.
						new PersonalDetails($userData->firstName, $userData->lastName, $userData->phone)
					)
				) {
					$user = $u;
					break;
				}
			}

			if ($user === null) {
				$user = new BookingUser();
			}

			$user->email = $userData->email;
			$user->personalDetails->firstName = $userData->firstName;
			$user->personalDetails->lastName = $userData->lastName;
			$user->personalDetails->phone = $userData->phone;
			$user->user = $userData->user;

			if (!$user->save()) {
				DB::rollback();
				$logger->error('Failed to create booking: user not saved', $command->getLogData());
				return null; // Failed to save user
			}

			if ($user->user !== null) {
				$user->user->personalDetails->firstName = $userData->firstName;
				$user->user->personalDetails->lastName = $userData->lastName;
				$user->user->personalDetails->phone = $userData->phone;
				if (!$user->user->save()) {
					// Only log error, we can live with this.
					$logger->error('Failed to update logged in user\'s personal details', [
						'userId'      => $user->user->id,
						'bookingData' => $command->getLogData(),
					]);
				}
			}
			$booking->users->add($user);
		}

		$booking->arena = $command->arena;
		$booking->type = $command->type;
		$booking->subtype = $command->subtype;

		$datetime = $this->bookingCalendarProvider->getSlotTime($command->datetime, $command->type, $command->subtype);

		// Convert to immutable DateTime
		if (!$datetime instanceof DateTimeImmutable) {
			$datetime = DateTimeImmutable::createFromInterface($datetime);
		}

		$booking->datetime = $datetime;

		$booking->addSlots(...$slots);

		$booking->locked = $command->locked || $command->subtype?->slotFill;
		$booking->note = $command->note;
		$booking->privateNote = $command->privateNote;
		$booking->subtypeFieldsParsed = $command->subtypeFields;
		$booking->terms = $command->terms;
		$booking->discovery = $command->discovery;
		$booking->customDiscovery = $command->customDiscovery;

		if (!$booking->save()) {
			DB::rollback();
			$logger->error('Failed to create booking: booking not saved', $command->getLogData());
			return null; // Failed to save booking
		}

		if (!$booking->saveBookingSlots()) {
			DB::rollback();
			$logger->error('Failed to create booking: slots not saved', $command->getLogData());
			return null; // Failed to save slots
		}

		DB::commit();
		$logger->info('Created new booking', $command->getLogData());

		// Create a google calendar event if setup for arena
		if (!empty($command->type->calendarId) && $command->arena->googleSettings->isReady()) {
			$client = $this->googleFactory->getClient($command->arena);
			foreach ($booking->slots as $slot) {
				$response = $this->commandBus->dispatch(
					new CreateCalendarEventCommand(
						$client,
						$command->type->calendarId,
						$slot->summary,
						$slot->datetime,
						$slot->end,
						$slot->description,
					)
				);
				if ($response->success) {
					$slot->eventId = $response->event?->id;
					if (!$slot->save()) {
						$logger->error(
							'Failed to save booking with calendar event ID',
							[
								'bookingId' => $booking->id,
								'eventId'   => $booking->eventId,
							]
						);
					}
				}
				else {
					$logger->error(
						'Failed to create calendar event for booking',
						[
							'bookingId' => $booking->id,
							'error'     => $response->error,
						]
					);
					if ($command->isAdmin) {
						$this->session->flashWarning(
							lang('Nepodařilo se uložit událost v google kalendáři.', context: 'errors', domain: 'booking'),
						);
					}
				}
			}
			$this->googleFactory->maybeRefreshToken($command->arena, $client);
		}

		$this->logger->addLog(
			$booking,
			LogAction::CREATED,
		);

		// TODO: Send notifications if needed

		return $booking;
	}
}
