<?php
declare(strict_types=1);

namespace App\Response\Booking;

use App\Models\Booking\Booking;
use App\Models\Booking\BookingSlot;
use App\Models\Booking\BookingUser;
use App\Models\Booking\Enums\BookingStatus;
use OpenApi\Attributes as OA;

#[OA\Schema]
readonly class BookingResponse
{

	/**
	 * @param BookingUserResponse[]  $users
	 * @param BookingSlotResponse[]  $slots
	 * @param BookingFieldResponse[] $fields
	 */
	public function __construct(
		#[OA\Property]
		public int                     $id,
		#[OA\Property]
		public int                     $arena,
		#[OA\Property]
		public BookingTypeResponse     $type,
		#[OA\Property]
		public ?BookingSubTypeResponse $subtype,
		#[OA\Property(description: 'List of users in this booking', type: 'array', items: new OA\Items(
			ref: '#/components/schemas/BookingUserResponse'
		))]
		public array                   $users,
		#[OA\Property]
		public BookingStatus           $status,
		#[OA\Property]
		public \DateTimeImmutable      $date,
		#[OA\Property(description: 'List of time slots occupied by this booking', type: 'array', items: new OA\Items(
			ref: '#/components/schemas/BookingSlotResponse'
		))]
		public array                   $slots,
		#[OA\Property]
		public bool                    $locked,
		#[OA\Property]
		public ?string                 $note,
		#[OA\Property]
		public ?string                 $privateNote,
		#[OA\Property]
		public ?array                  $fields,
	) {
	}

	public static function fromBooking(Booking $booking): self {
		return new self(
			$booking->id,
			$booking->arena->id,
			BookingTypeResponse::fromType($booking->type),
			$booking->subtype !== null ? BookingSubTypeResponse::fromSubType($booking->subtype) : null,
			array_values($booking->users->map(static fn(BookingUser $user) => BookingUserResponse::fromUser($user))),
			$booking->status,
			$booking->datetime,
			array_values($booking->slots->map(static fn(BookingSlot $slot) => BookingSlotResponse::fromSlot($slot))),
			$booking->locked,
			$booking->note,
			$booking->privateNote,
			array_map(
				static fn($key, $value) => BookingFieldResponse::fromValueAndSubtype($key, $value, $booking->subtype),
				array_keys($booking->subtypeFieldsParsed),
				array_values($booking->subtypeFieldsParsed)
			),
		);
	}

}