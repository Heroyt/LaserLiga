<?php

declare(strict_types=1);

namespace App\CQRS\Commands\Booking;

use App\CQRS\CommandHandlers\Booking\DeleteBookingCommandHandler;
use App\CQRS\CommandResponses\Booking\DeleteBookingCommandResponse;
use App\Models\Booking\Booking;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<DeleteBookingCommandResponse>
 */
final readonly class DeleteBookingCommand implements CommandInterface
{
	public function __construct(
		public Booking $booking,
		public bool $notifyUser = true,
		public bool $notifyAdmin = true,
		public ?string $cancellationReason = null,
	)
	{
	}


	public function getHandler(): string
	{
		return DeleteBookingCommandHandler::class;
	}
}
