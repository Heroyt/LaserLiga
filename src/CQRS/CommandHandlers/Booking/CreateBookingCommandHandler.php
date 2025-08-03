<?php

declare(strict_types=1);

namespace App\CQRS\CommandHandlers\Booking;

use App\CQRS\Commands\Booking\CreateBookingCommand;
use App\CQRS\Commands\Google\CreateCalendarEventCommand;
use App\Models\Auth\PersonalDetails;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingUser;
use App\Services\Booking\BookingCalendarProvider;
use App\Services\Google\GoogleClientFactory;
use DateTimeImmutable;
use Lsr\CQRS\CommandBus;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Db\DB;
use Lsr\Logging\Logger;

final readonly class CreateBookingCommandHandler implements CommandHandlerInterface
{
	public function __construct(
		private BookingCalendarProvider $bookingCalendarProvider,
		private GoogleClientFactory     $googleFactory,
		private CommandBus              $commandBus,
	) {
	}

	/**
	 * @param CreateBookingCommand $command
	 */
	public function handle(CommandInterface $command): ?Booking {
		$logger = new Logger(LOG_DIR . 'booking/', 'new-bookings');

		// Convert datetime to valid slot time
		$datetime = $this->bookingCalendarProvider->getSlotTime($command->datetime, $command->type, $command->subtype);

		// Check if booking slot is available
		$isAvailable = $this->bookingCalendarProvider->isSlotAvailable(
			$datetime,
			$command->type,
			$command->subtype,
			$command->playerCount,
			$command->allowAllTimes,
			$command->allowOverbooking,
		);

		if (!$isAvailable) {
			$logger->warning('Failed to create booking: slot is not available', $command->getLogData());
			return null; // Booking slot is not available
		}

		// Convert to immutable DateTime
		if (!$datetime instanceof DateTimeImmutable) {
			$datetime = DateTimeImmutable::createFromInterface($datetime);
		}

		DB::begin(); // Start a transaction

		$booking = new Booking();

		// Create users
		foreach ($command->users as $userData) {
			$user = BookingUser::findByEmail($userData->email);

			// If user already exists, check other detail to make sure the user is the same
			if (
				$user !== null
				&& $user->user?->id !== $userData->user?->id // If user is logged in, we can assume the user is the same.
				&& !$user->personalDetails->matches( // Checks normalized values.
					new PersonalDetails($userData->firstName, $userData->lastName, $userData->phone)
				)
			) {
				$user = null; // User detail do not match
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
			$booking->users->add($user);
		}

		$booking->arena = $command->arena;
		$booking->type = $command->type;
		$booking->subtype = $command->subtype;
		$booking->datetime = $datetime;
		$booking->playerCount = $command->playerCount;
		$booking->slots = $command->slots;
		$booking->locked = $command->locked;
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

		DB::commit();
		$logger->info('Created new booking', $command->getLogData());

		// Create a google calendar event if setup for arena
		if ($command->arena->googleSettings->isReady()) {
			$response = $this->commandBus->dispatch(
				new CreateCalendarEventCommand(
					$this->googleFactory->getClient($command->arena),
					$command->type->calendarId,
					$booking->summary,
					$booking->datetime,
					$booking->end,
					$booking->description,
				)
			);
			if ($response->success) {
				$booking->eventId = $response->event?->id;
				if (!$booking->save()) {
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
			}
		}

		return $booking;
	}
}
