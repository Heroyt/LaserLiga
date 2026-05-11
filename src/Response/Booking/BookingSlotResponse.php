<?php
declare(strict_types=1);

namespace App\Response\Booking;

use App\Models\Booking\BookingSlot;
use OpenApi\Attributes as OA;

#[OA\Schema]
readonly class BookingSlotResponse
{

	public function __construct(
		#[OA\Property(format: 'time')]
		public string $time,
		#[OA\Property]
		public int $span,
		#[OA\Property]
		public int $playerCount,
		#[OA\Property]
		public string $summary,
		#[OA\Property]
		public string $description,
	) {
	}

	public static function fromSlot(BookingSlot $slot): self {
		return new self(
			$slot->time->format('H:i'),
			$slot->span,
			$slot->playerCount,
			$slot->summary,
			$slot->description,
		);
	}

}