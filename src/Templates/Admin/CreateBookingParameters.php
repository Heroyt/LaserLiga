<?php
declare(strict_types=1);

namespace App\Templates\Admin;

use App\Models\Arena;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\BookingType;
use App\Models\Booking\DataObjects\BookingTimeStatus;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class CreateBookingParameters extends TemplateParameters
{

	use AutoFillParameters;
	use PageTemplateParameters;

	public \DateTimeImmutable $datetime;
	public Arena $arena;
	public BookingType $type;
	/** @var BookingSubType[] */
	public array $subtypes = [];
	/** @var BookingTimeStatus[] */
	public array $slots = [];

}