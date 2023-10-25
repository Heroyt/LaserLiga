<?php

namespace App\Models;

use Dibi\Row;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;

class Address implements InsertExtendInterface
{

	public function __construct(
		public ?string $street = null,
		public ?string $city = null,
		public ?string $postCode = null,
		public ?string $country = null,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row): ?static {
		return new self(
			$row->address_street ?? null,
			$row->address_city ?? null,
			$row->address_post_code ?? null,
			$row->address_country ?? null,
		);
	}

	public function isFilled(): bool {
		return isset($this->street) || isset($this->city);
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data): void {
		$data['address_street'] = $this->street;
		$data['address_city'] = $this->city;
		$data['address_post_code'] = $this->postCode;
		$data['address_country'] = $this->country;
	}

	public function __toString(): string {
		$return = $this->street ?? '';

		if (isset($this->city)) {
			$return .= ', ' . $this->city;
		}

		if (isset($this->postCode)) {
			$return .= ' ' . $this->postCode;
		}

		return $return;
	}
}