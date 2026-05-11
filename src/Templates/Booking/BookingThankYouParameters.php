<?php
declare(strict_types=1);

namespace App\Templates\Booking;

use App\Models\Arena;
use App\Models\Booking\Booking;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class BookingThankYouParameters extends TemplateParameters
{
	use AutoFillParameters;
	use PageTemplateParameters;

	public Arena $arena;
	public ?Booking $booking = null;

}