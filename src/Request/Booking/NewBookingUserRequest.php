<?php
declare(strict_types=1);

namespace App\Request\Booking;

use App\Models\Auth\User;
use App\Models\Booking\BookingUser;
use Lsr\ObjectValidation\Attributes\Email;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\ObjectValidation\Exceptions\ValidationMultiException;

class NewBookingUserRequest
{

	public ?int $id = null;

	#[
		Required('Jméno je povinné'),
		StringLength(min: 1, max: 50, message: 'Jméno musí mít mezi 1 a 50 znaky'),
	]
	public string $name;

	#[
		Required('Příjmení je povinné'),
		StringLength(min: 1, max: 50, message: 'Příjmení musí mít mezi 1 a 50 znaky'),
	]
	public string $surname;

	#[Required('E-mail je povinný'), Email('E-mail není platný')]
	public string $email;

	#[
		Required('Telefon je povinný'),
		StringLength(min: 1, max: 20, message: 'Telefon je povinný'),
	]
	public string $phone;

	public ?int $user = null;

	public function validate() : void {
		/** @var ValidationException[] $exceptions */
		$exceptions = [];

		if ($this->id !== null && !BookingUser::exists($this->id)) {
			$exceptions[] = ValidationException::createWithCustomMessage(
				$this,
				'id',
				'Hráč neexistuje',
				$this->id,
			);
		}

		if ($this->user !== null && !User::exists($this->user)) {
			$exceptions[] = ValidationException::createWithCustomMessage(
				$this,
				'id',
				'Hráč neexistuje',
				$this->user,
			);
		}


		if (!empty($exceptions)) {
			throw new ValidationMultiException($exceptions);
		}
	}

}