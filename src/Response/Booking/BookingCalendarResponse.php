<?php
declare(strict_types=1);

namespace App\Response\Booking;

use OpenApi\Attributes as OA;

#[OA\Schema]
class BookingCalendarResponse
{

	/**
	 * @param BookingTimeStatusResponse[] $slots
	 * @param BookingResponse[]           $bookings
	 */
	public function __construct(
		#[OA\Property(description: 'Date for which the calendar is generated', format: 'date', example: '2023-10-15')]
		public string $date,
		#[OA\Property(description: 'List of booking slots for the user', type: 'array', items: new OA\Items(ref: '#/components/schemas/BookingSlotResponse'))]
		public array $slots = [],
		#[OA\Property(description: 'List of bookings for the user', type: 'array', items: new OA\Items(ref: '#/components/schemas/BookingResponse'))]
		public array $bookings = [],
		#[OA\Property(description: 'Optional note for the day', nullable: true)]
		public ?string $note = null,
	){}

}