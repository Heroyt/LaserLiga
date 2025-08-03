<?php
declare(strict_types=1);

namespace App\Models\Auth;

use App\Helpers\PhoneNumber;
use Dibi\Row;
use Lsr\Helpers\Tools\Strings;
use Lsr\ObjectValidation\Attributes\StringLength;
use Lsr\Orm\Interfaces\InsertExtendInterface;

class PersonalDetails implements InsertExtendInterface
{

	public function __construct(
		#[StringLength(max: 50)]
		public ?string $firstName = null,
		#[StringLength(max: 50)]
		public ?string $lastName = null,
		#[StringLength(max: 20)]
		public ?string $phone = null,
	){}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row): ?static {
		return new static(
			firstName: empty($row->first_name) ? null : $row->first_name,
			lastName: empty($row->last_name) ? null : $row->last_name,
			phone: empty($row->phone) ? null : $row->phone,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data): void {
		$data['first_name'] = $this->firstName;
		$data['last_name'] = $this->lastName;
		$data['phone'] = $this->phone;
	}

	public function matches(PersonalDetails $other) : bool {
		if ($this->normalizeName($this->firstName) !== $this->normalizeName($other->firstName)) {
			return false;
		}
		if ($this->normalizeName($this->lastName) !== $this->normalizeName($other->lastName)) {
			return false;
		}
		if ($this->normalizePhone($this->phone) !== $this->normalizePhone($other->phone)) {
			return false;
		}
		return true;
	}

	private function normalizePhone(?string $phone): ?string {
		if ($phone === null) {
			return null;
		}
		// Normalize number and removes country prefix
		return new PhoneNumber($phone)->number;
	}

	private function normalizeName(?string $name): ?string {
		if ($name === null) {
			return null;
		}
		return trim(Strings::lower(Strings::toAscii($name)));
	}
}