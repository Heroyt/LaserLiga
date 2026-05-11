<?php
declare(strict_types=1);

namespace App\Templates\Booking;

use App\Models\Arena;
use App\Models\Auth\User;
use App\Models\Booking\BookingType;
use App\Models\Booking\Discovery;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use App\Widget\Calendar;
use Lsr\Core\Controllers\TemplateParameters;

class BookingShowParameters extends TemplateParameters
{

	use PageTemplateParameters;
	use AutoFillParameters;

	public Arena $arena;
	/** @var BookingType[] */
	public array $types = [];
	public Calendar $calendar;
	/** @var Discovery[] */
	public array $discoveries = [];

	public ?User $user = null;

}