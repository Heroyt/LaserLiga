<?php

declare(strict_types=1);

namespace App\CQRS\CommandHandlers\Booking;

use App\CQRS\CommandResponses\Booking\DeleteBookingCommandResponse;
use App\CQRS\Commands\Booking\DeleteBookingSlotCommand;
use App\CQRS\Commands\Google\RemoveCalendarEventCommand;
use App\Models\Booking\Enums\LogAction;
use App\Services\Booking\BookingLogger;
use App\Services\Google\GoogleClientFactory;
use Google\Exception;
use Lsr\CQRS\CommandBus;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Logging\Logger;

final readonly class DeleteBookingSlotCommandHandler implements CommandHandlerInterface
{
	public function __construct(
		private GoogleClientFactory $googleClientFactory,
		private CommandBus          $commandBus,
		private BookingLogger           $logger,
	) {
	}

	/**
	 * @param DeleteBookingSlotCommand $command
	 */
	public function handle(CommandInterface $command): DeleteBookingCommandResponse {
		$logger = new Logger(LOG_DIR . 'booking/', 'delete-bookings');

		$booking = $command->booking;
		$slot = $command->slot;

		// Remove calendar event
		if (
			!empty($booking->type->calendarId)
			&& !empty($slot->eventId)
			&& $booking->arena->googleSettings->isReady()
		) {
			try {
				$client = $this->googleClientFactory->getClient($booking->arena);
			} catch (Exception $e) {
				$logger->exception($e);
				return new DeleteBookingCommandResponse(
					success  : false,
					error    : $e->getMessage(),
					exception: $e,
				);
			}

			$response = $this->commandBus->dispatch(
				new RemoveCalendarEventCommand(
					$client,
					$booking->type->calendarId,
					$slot->eventId
				)
			);

			$this->googleClientFactory->maybeRefreshToken($booking->arena, $client);

			if (!$response->success) {
				$logger->error('Failed to remove calendar event', [
					'booking_id'  => $booking->id,
					'slot_id'     => $slot->id,
					'event_id'    => $slot->eventId,
					'calendar_id' => $booking->type->calendarId,
					'error'       => $response->error,
					'exception'   => $response->exception?->getMessage(),
				]);
				return new DeleteBookingCommandResponse(
					success  : false,
					error    : $response->error,
					exception: $response->exception,
				);
			}
		}

		if (!$slot->delete()) {
			$logger->error('Failed to delete booking from database', [
				'booking_id' => $booking->id,
			]);
			return new DeleteBookingCommandResponse(
				success: false,
				error  : 'Failed to delete booking from database.',
			);
		}

		$booking->slots->remove($slot);

		// TODO: Send cancellation emails if needed

		$this->logger->addLog(
			$booking,
			LogAction::SLOT_CHANGED,
			lang('Odstraněn čas rezervace', context: 'log', domain: 'booking').':'.$slot->time->format('H:i'),
		);

		return new DeleteBookingCommandResponse(true);
	}
}
