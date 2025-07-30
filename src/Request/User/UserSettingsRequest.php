<?php
declare(strict_types=1);

namespace App\Request\User;

use Lsr\ObjectValidation\Attributes\Email;
use Lsr\ObjectValidation\Attributes\Regex;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;
use Lsr\ObjectValidation\Attributes\Url;

class UserSettingsRequest
{

	#[Required('Jméno je povinné'), StringLength(min: 3, max: 50, message: 'Jméno musí mít alespoň 3 znaky a maximálně 50 znaků')]
	public string $name;
	#[Required('E-mail je povinný'), Email('E-mail nemá správný formát')]
	public string $email;
	public ?\DateTimeImmutable $birthday = null;
	/** @var int|numeric-string  */
	public int|string $arena = 0;
	/** @var int|numeric-string  */
	public int|string $title = 0;
	#[Url('URL nemá správný formát')]
	public ?string $mylasermaxx = null;
	#[Regex('/\d{2,}-\d+-\d{3,}/', 'Laserforce ID nemá správný formát')]
	public ?string $laserforce = null;

	public string $oldPassword = '';
	public string $password = '';

	public string $type = '';
	public string $seed = '';

	public function setBirthday(\DateTimeImmutable|string|null $date): void
	{
		if ($date instanceof \DateTimeImmutable) {
			$this->birthday = $date;
		} elseif (is_string($date) && $date !== '') {
			$this->birthday = new \DateTimeImmutable($date);
		} else {
			$this->birthday = null;
		}
	}

}