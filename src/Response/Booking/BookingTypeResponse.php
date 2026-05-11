<?php
declare(strict_types=1);

namespace App\Response\Booking;

use App\Models\Booking\BookingType;
use OpenApi\Attributes as OA;

#[OA\Schema]
readonly class BookingTypeResponse
{

	public function __construct(
		#[OA\Property]
		public int $id,
		#[OA\Property]
		public string     $icon,
		#[OA\Property]
		public string $name,
		#[OA\Property]
		public int $slotLength,
		#[OA\Property]
		public int $slotLimit,
		#[OA\Property]
		public bool $openable,
		#[OA\Property]
		public int $openableMin,
	) {
	}

	public static function fromType(BookingType $type): self {
		return new self(
			$type->id,
			$type->getIconHTML(),
			$type->getTranslatedName(),
			$type->slotLength,
			$type->slotLimit,
			$type->openable,
			$type->openableMin,
		);
	}

}