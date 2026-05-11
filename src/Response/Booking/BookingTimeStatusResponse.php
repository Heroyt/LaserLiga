<?php
declare(strict_types=1);

namespace App\Response\Booking;

use App\Models\Booking\DataObjects\BookingTimeStatus;
use OpenApi\Attributes as OA;

#[OA\Schema]
readonly class BookingTimeStatusResponse
{

	public function __construct(
		#[OA\Property(format: 'date-time')]
		public string $datetime,
		#[OA\Property(format: 'time')]
		public string $time,
		#[OA\Property(enum: ['AVAILABLE', 'FILLED', 'ON_CALL', 'PARTIALLY_FILLED', 'CLOSED'])]
		public string $status,
		#[OA\Property(description: 'Number of available spots for this time slot', example: 5)]
		public int    $availableSpots,
	) {
	}

	public static function fromSlot(BookingTimeStatus $bookingSlot): self {
		return new self(
			$bookingSlot->datetime->format('c'),
			$bookingSlot->time,
			$bookingSlot->status->value,
			$bookingSlot->availableSpots,
		);
	}

}