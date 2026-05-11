<?php
declare(strict_types=1);

namespace App\Response\Booking;

use App\Models\Booking\BookingSubType;
use App\Models\Booking\Enums\FieldType;
use OpenApi\Attributes as OA;

#[OA\Schema]
readonly class BookingFieldResponse
{

	/**
	 * @param string|int|bool|string[] $value
	 * @param string|string[]|null   $valueLabel
	 */
	public function __construct(
		#[OA\Property]
		public string            $key,
		#[OA\Property]
		public string|int|array|bool  $value,
		#[OA\Property]
		public ?string           $label = null,
		#[OA\Property]
		public string|array|null $valueLabel = null,
		#[OA\Property]
		public FieldType $type = FieldType::TEXT,
	) {
	}


	/**
	 * @param string|int|bool|string[] $value
	 */
	public static function fromValueAndSubtype(string $key, string|int|bool|array $value, ?BookingSubType $subType): self {
		$field = null;
		foreach ($subType?->fields as $f) {
			if ($f->name === $key) {
				$field = $f;
				break;
			}
		}
		return new self(
			$key,
			$value,
			$field?->getTranslatedLabel(),
			is_array($value) ?
				$field?->getTranslatedLabelsForValues(null, ...$value)
				: $field?->getTranslatedLabelForValue((string) $value),
			$field->type ?? FieldType::TEXT,
		);
	}

}