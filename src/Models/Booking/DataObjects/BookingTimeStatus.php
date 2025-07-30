<?php
declare(strict_types=1);

namespace App\Models\Booking\DataObjects;


use App\Models\Booking\Booking;
use App\Models\Booking\Enums\TimeStatus;

class BookingTimeStatus
{
	/**
	 * @param string    $time           Time in the format 'H:i'
	 * @param int       $availableSpots How many players can still book this time slot?
	 * @param Booking[] $bookings       List of bookings that are associated with this time slot
	 */
	public function __construct(
		public string     $time,
		public TimeStatus $status,
		public int        $availableSpots,
		public array      $bookings = [],
	) {
	}
}