<?php

declare(strict_types=1);

namespace App\CQRS\CommandHandlers\Booking;

use App\CQRS\CommandResponses\Booking\UpdateBookingCommandResponse;
use App\CQRS\Commands\Booking\UpdateBookingCommand;
use App\CQRS\Commands\Google\CreateCalendarEventCommand;
use App\CQRS\Commands\Google\RemoveCalendarEventCommand;
use App\CQRS\Commands\Google\UpdateCalendarEventCommand;
use App\Models\Auth\PersonalDetails;
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

final readonly class UpdateBookingCommandHandler implements CommandHandlerInterface
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
	 * @param UpdateBookingCommand $command
	 */
	public function handle(CommandInterface $command): UpdateBookingCommandResponse {
		$logger = new Logger(LOG_DIR . 'booking/', 'updates');

		$booking = $command->booking;
		$date = $booking->datetime;
		$type = $booking->type;

		// Update slots
		/** @var string[] $prevSlots */
		$prevSlots = $booking->slots->map(
			static fn(BookingSlot $slot) => $slot->time->format('H:i')
		);
		$newSlots = [];
		$deleteSlots = [];
		$changedSlots = [];
		foreach ($command->slots as $time => $playerCount) {
			$slot = $booking->getSlot($time);
			if ($slot !== null) {
				if ($slot->playerCount !== $playerCount) {
					$slot->playerCount = $playerCount;
					$changedSlots[$time] = $slot;
				}
				continue;
			}

			[$hour, $minute] = explode(':', $time);
			$slotDate = $date->setTime((int)$hour, (int)$minute);

			// Convert datetime to valid slot time
			$datetime = $this->bookingCalendarProvider->getSlotTime($slotDate, $type, $command->subtype);

			// Check if booking slot is available
			$isAvailable = $this->bookingCalendarProvider->isSlotAvailable(
				$datetime,
				$type,
				$command->subtype,
				$playerCount,
				$command->allowAllTimes,
				$command->allowOverbooking,
			);

			if (!$isAvailable) {
				$logger->warning(
					'Failed to update booking: slot is not available',
					[
						'data' => $command->getLogData(),
						'datetime' => $datetime,
					]
				);
				return new UpdateBookingCommandResponse(
					false,
					'Booking slot is not available',
				); // Booking slot is not available
			}

			// Convert to immutable DateTime
			if (!$datetime instanceof DateTimeImmutable) {
				$datetime = DateTimeImmutable::createFromInterface($datetime);
			}

			$slot = new BookingSlot();
			$slot->time = $datetime;
			$slot->playerCount = $playerCount;
			$newSlots[$time] = $slot;
		}
		// Find slots to delete
		foreach ($booking->slots as $slot) {
			$time = $slot->time->format('H:i');
			if (!isset($command->slots[$time])) {
				$deleteSlots[] = $slot;
			}
		}

		$slotChange = !empty($newSlots) || !empty($deleteSlots) || !empty($changedSlots);
		if ($slotChange) {
			DB::begin(); // Start a transaction

			// Delete old slots
			foreach ($deleteSlots as $slot) {
				$booking->slots->remove($slot);
				if (!$slot->delete()) {
					$logger->error('Failed to delete booking slot', $command->getLogData());
					DB::rollback();
					return new UpdateBookingCommandResponse(false, 'Failed to delete old booking slots');
				}
			}
			// Add new slots
			$booking->addSlots(...$newSlots);

			if (!$booking->saveBookingSlots()) {
				$logger->error('Failed to save new booking slots', $command->getLogData());
				DB::rollback();
				return new UpdateBookingCommandResponse(false, 'Failed to save new booking slots');
			}

			/** @var string[] $newSlots */
			$newSlots = $booking->slots->map(
				static fn(BookingSlot $slot) => $slot->time->format('H:i')
			);
			$this->logger->addLog(
				$booking,
				LogAction::SLOT_CHANGED,
				implode(', ', $prevSlots) . ' => ' . implode(', ', $newSlots),
			);
			DB::commit();
		}

		DB::begin();

		// Update booking users
		$bookingChanged = false;
		foreach ($command->users as $userData) {
			// Existing user
			if ($userData->id !== null) {
				$user = BookingUser::get($userData->id);

				$user->email = $userData->email;
				$user->personalDetails->firstName = $userData->firstName;
				$user->personalDetails->lastName = $userData->lastName;
				$user->personalDetails->phone = $userData->phone;
				$user->user = $userData->user;

				$bookingChanged = $bookingChanged || !empty($user->getChangedProperties());

				if (!$user->save()) {
					DB::rollback();
					$logger->error('Failed to create booking: user not saved', $command->getLogData());
					return new UpdateBookingCommandResponse(false, 'Failed to save booking user');
				}
				continue;
			}

			// Check for duplicate users
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

			$bookingChanged = $bookingChanged || !empty($user->getChangedProperties());

			if (!$user->save()) {
				DB::rollback();
				$logger->error('Failed to create booking: user not saved', $command->getLogData());
				return new UpdateBookingCommandResponse(
					false, 'Failed to insert new booking user'
				); // Failed to save user
			}
			$booking->users->add($user);
		}

		// Update booking details
		$booking->subtype = $command->subtype;
		$datetime = $this->bookingCalendarProvider->getSlotTime($booking->datetime, $booking->type, $command->subtype);
		// Convert to immutable DateTime
		if (!$datetime instanceof DateTimeImmutable) {
			$datetime = DateTimeImmutable::createFromInterface($datetime);
		}
		$booking->datetime = $datetime;
		$booking->locked = $command->locked || $command->subtype?->slotFill;
		$booking->note = $command->note;
		$booking->privateNote = $command->privateNote;
		$booking->subtypeFieldsParsed = $command->subtypeFields;
		$booking->terms = $command->terms;
		$booking->discovery = $command->discovery;
		$booking->customDiscovery = $command->customDiscovery;

		$bookingChanged = $bookingChanged || !empty($booking->getChangedProperties());
		if (!$booking->save()) {
			DB::rollback();
			$logger->error('Failed to create booking: booking not saved', $command->getLogData());
			return new UpdateBookingCommandResponse(false, 'Failed to save booking'); // Failed to save booking
		}


		// Log change
		if ($bookingChanged) {
			$this->logger->addLog(
				$booking,
				LogAction::UPDATED,
			);
		}

		DB::commit();
		$logger->info('Updated booking', $command->getLogData());

		// Update google calendar event
		if (!empty($booking->type->calendarId) && $booking->arena->googleSettings->isReady()) {
			$client = $this->googleFactory->getClient($booking->arena);
			foreach ($booking->slots as $slot) {
				if (!empty($slot->eventId)) {
					// Update event
					$response = $this->commandBus->dispatch(
						new UpdateCalendarEventCommand(
							$client,
							$booking->type->calendarId,
							$slot->eventId,
							$slot->summary,
							$slot->datetime,
							$slot->end,
							$slot->description,
						)
					);
					if (!$response->success) {
						$logger->error(
							'Failed to save calendar event for booking',
							[
								'bookingId' => $booking->id,
								'eventId' => $slot->eventId,
								'error'     => $response->error,
								'exception' => $response->exception
							]
						);
						if ($command->isAdmin) {
							$this->session->flashWarning(
								lang('Nepodařilo se uložit událost v google kalendáři.', context: 'errors', domain: 'booking'),
							);
						}
					}
					continue;
				}
				// Create new event
				$response = $this->commandBus->dispatch(
					new CreateCalendarEventCommand(
						$client,
						$booking->type->calendarId,
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
			foreach ($deleteSlots as $slot) {
				if (empty($slot->eventId)) {
					continue;
				}
				// Delete event
				$response = $this->commandBus->dispatch(
					new RemoveCalendarEventCommand(
						$client,
						$booking->type->calendarId,
						$slot->eventId,
					)
				);
				if (!$response->success) {
					$logger->error(
						'Failed to delete calendar event for booking',
						[
							'bookingId' => $booking->id,
							'eventId'   => $slot->eventId,
							'error'     => $response->error,
						]
					);
				}
			}
			$this->googleFactory->maybeRefreshToken($booking->arena, $client);
		}

		// TODO: Send notifications if needed

		return new UpdateBookingCommandResponse(true);
	}
}
