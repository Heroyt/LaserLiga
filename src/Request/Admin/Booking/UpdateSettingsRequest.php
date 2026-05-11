<?php
declare(strict_types=1);

namespace App\Request\Admin\Booking;

use App\Core\ObjectValidators\ValidateEach;
use Lsr\ObjectValidation\Attributes\Email;

class UpdateSettingsRequest
{

	public string $bookingEmails = '';

	#[Email('Zadejte platný e-mail', true)]
	public string $replyToEmail = '';

	/** @var array<int, BookingTypeRequest> */
	#[ValidateEach]
	public array $types = [];

	/** @var array<int, BookingSubtypeRequest>  */
	#[ValidateEach]
	public array $subtypes = [];

	public function addSubtype(BookingSubtypeRequest $subtype): void
	{
		$this->subtypes[] = $subtype;
	}

	public function addType(BookingTypeRequest $type): void
	{
		$this->types[] = $type;
	}

	public function validate() : void {
		$emails = array_filter(array_map('trim', explode(",", $this->bookingEmails)));
		foreach ($emails as $email) {
			new Email('Zadejte platné e-maily oddělené čárkou')->validateValue($email, $this, 'bookingEmails');
		}
	}

}