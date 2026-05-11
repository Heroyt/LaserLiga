<?php
declare(strict_types=1);

namespace App\Request\Booking;

class BookingAdminRequest extends NewBookingRequest
{

	protected const bool VALIDATE_TIMES = false;

	public string $privateNote = '';

}