<?php
declare(strict_types=1);

namespace App\Models\Booking\DataObjects;

use App\Models\Auth\User;

final readonly class BookingUserData
{

	public function __construct(
		public string $email,
		public ?string $firstName = null,
		public ?string $lastName = null,
		public ?string $phone = null,
		public ?User $user = null,
	){}

	public static function createFromUser(User $user): self
	{
		return new self(
			email: $user->email,
			firstName: $user->personalDetails->firstName,
			lastName: $user->personalDetails->lastName,
			phone: $user->personalDetails->phone,
			user: $user
		);
	}

}