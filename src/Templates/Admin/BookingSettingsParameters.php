<?php
declare(strict_types=1);

namespace App\Templates\Admin;

use App\Models\Arena;
use App\Models\Booking\BookingType;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Google\Service\Calendar\CalendarListEntry;
use Lsr\Core\Controllers\TemplateParameters;

class BookingSettingsParameters extends TemplateParameters
{

	use AutoFillParameters;
	use PageTemplateParameters;

	public Arena $arena;

	/** @var BookingType[] */
	public array $bookingTypes = [];

	/** @var CalendarListEntry[] */
	public array $googleCalendars = [];

}