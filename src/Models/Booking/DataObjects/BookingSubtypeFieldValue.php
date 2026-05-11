<?php
declare(strict_types=1);

namespace App\Models\Booking\DataObjects;

use JsonSerializable;
use Nette\Utils\Validators;

class BookingSubtypeFieldValue implements JsonSerializable
{

	public function __construct(
		public int    $key,
		public string $value,
		public string $label,
		public bool   $default = false,
		public string $info = '',
	) {
	}

	public function isInfoUrl(): bool {
		return Validators::isUrl($this->info);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function jsonSerialize(): array {
		return get_object_vars($this);
	}
}