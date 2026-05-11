<?php

declare(strict_types=1);

namespace App\CQRS\Commands\Booking;

use App\CQRS\CommandHandlers\Booking\DeleteBookingSlotCommandHandler;
use App\CQRS\CommandResponses\Booking\DeleteBookingCommandResponse;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingSlot;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<DeleteBookingCommandResponse>
 */
final readonly class DeleteBookingSlotCommand implements CommandInterface
{
	public function __construct(
		public Booking $booking,
		public BookingSlot $slot,
		public bool $notifyUser = true,
		public bool $notifyAdmin = true,
		public ?string $cancellationReason = null,
	)
	{
	}


	public function getHandler(): string
	{
		return DeleteBookingSlotCommandHandler::class;
	}
}
