<?php
declare(strict_types=1);

namespace App\Widget\Factory;

use App\Models\Arena;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\BookingType;
use App\Services\Booking\BookingCalendarProvider;
use App\Widget\Calendar;
use Lsr\Core\App;
use Lsr\Core\Templating\Latte;
use Symfony\Component\Serializer\Serializer;

readonly class CalendarWidgetFactory
{

	public function __construct(
		private BookingCalendarProvider $calendarProvider,
		private Latte                   $latte,
		private Serializer              $serializer,
	) {
	}

	public function create(
		Arena $arena,
		int             $year = 0,
		int             $month = 0,
		int             $day = 0,
		string          $selectedDate = 'now',
		?BookingType    $type = null,
		?BookingSubtype $subType = null,
	): Calendar {
		$this->latte->setLocale(App::getInstance()->translations->getLang());
		return new Calendar(
			$this->calendarProvider,
			$this->latte,
			$this->serializer,
			$arena,
			$year,
			$month,
			$day,
			$selectedDate,
			$type,
			$subType,
		);
	}

}