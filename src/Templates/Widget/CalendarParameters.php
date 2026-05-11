<?php
declare(strict_types=1);

namespace App\Templates\Widget;

use App\Models\Arena;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\BookingType;
use App\Models\Booking\DataObjects\BookingTimeStatus;
use App\Models\Booking\DataObjects\CalendarDay;
use Lsr\Core\Controllers\TemplateParameters;

class CalendarParameters extends TemplateParameters
{

	public Arena $arena;
	public int $slotLimit = 11;
	public BookingType $type;
	public ?BookingSubType $subType = null;
	public int $year = 0;
	public int $month = 0;
	public int $day = 0;
	/** @var array<int,string> */
	public array $days = [];
	/** @var array<int,string> */
	public array $months = [];
	public string $selected = '';
	/** @var array<int, array<1|2|3|4|5|6|7, null|CalendarDay>>  */
	public array $weeks = [];

	/** @var array<string, BookingTimeStatus> */
	public array $times                 = [];
	public bool  $previousMonthDisabled = false;
	public string $todayDay = '';
	public string $todayMonth = '';
	public string $todayYear = '';
	public string $nextMonth = '';
	public string $previousMonth = '';
	public string $nextMonthYear = '';
	public string $previousMonthYear = '';
	public string $configuration = '';

}