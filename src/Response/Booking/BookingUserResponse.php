<?php
declare(strict_types=1);

namespace App\Response\Booking;

use App\Models\Booking\BookingUser;
use OpenApi\Attributes as OA;

#[OA\Schema]
readonly class BookingUserResponse
{

	public function __construct(
		#[OA\Property]
		public int     $id,
		#[OA\Property]
		public string  $email,
		#[OA\Property]
		public string $firstName,
		#[OA\Property]
		public string $lastName,
		#[OA\Property]
		public string $phone,
		#[OA\Property]
		public ?string $user,
	) {
	}

	public static function fromUser(BookingUser $user): self {
		return new self(
			$user->id,
			$user->email,
			$user->personalDetails->firstName,
			$user->personalDetails->lastName,
			$user->personalDetails->phone,
			$user->user?->player?->getCode(),
		);
	}

}