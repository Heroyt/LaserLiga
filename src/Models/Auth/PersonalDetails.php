<?php
declare(strict_types=1);

namespace App\Models\Auth;

use Dibi\Row;
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
}