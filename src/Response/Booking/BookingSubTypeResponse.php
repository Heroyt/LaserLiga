<?php
declare(strict_types=1);

namespace App\Response\Booking;

use App\Models\Booking\BookingSubType;
use OpenApi\Attributes as OA;

#[OA\Schema]
readonly class BookingSubTypeResponse
{

	public function __construct(
		#[OA\Property]
		public int     $id,
		#[OA\Property]
		public string     $icon,
		#[OA\Property]
		public string  $name,
		#[OA\Property]
		public ?string $description,
		#[OA\Property]
		public ?string $datetimeDescription,
		#[OA\Property]
		public ?string $infoDescription,
	) {
	}

	public static function fromSubType(BookingSubType $type): self {
		return new self(
			$type->id,
			$type->getIconHTML(),
			$type->getTranslatedName(),
			$type->getTranslatedDescription(),
			$type->getTranslatedDatetimeDescription(),
			$type->getTranslatedInfoDescription(),
		);
	}

}