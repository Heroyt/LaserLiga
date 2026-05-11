<?php
declare(strict_types=1);

namespace App\Widget;

use App\CQRS\Queries\Booking\BookingTimeSlotsQuery;
use App\Models\Arena;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\BookingType;
use App\Models\Booking\DataObjects\BookingTimeStatus;
use App\Models\Booking\DataObjects\CalendarDay;
use App\Models\Booking\Enums\TimeStatus;
use App\Services\Booking\BookingCalendarProvider;
use App\Templates\Widget\CalendarParameters;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Lsr\Core\Templating\Latte;
use Symfony\Component\Serializer\Serializer;
use Throwable;

class Calendar
{

	public const array MONTHS = [
		1  => "Leden",
		2  => "Únor",
		3  => "Březen",
		4  => "Duben",
		5  => "Květen",
		6  => "Červen",
		7  => "Červenec",
		8  => "Srpen",
		9  => "Září",
		10 => "Říjen",
		11 => "Listopad",
		12 => "Prosinec",
	];
	public const array DAYS   = [
		1 => 'Po',
		2 => 'Út',
		3 => 'St',
		4 => 'Čt',
		5 => 'Pá',
		6 => 'So',
		7 => 'Ne',
	];
	public BookingType     $type;
	public ?BookingSubtype $subType = null;
	/**
	 * @var DateTime
	 */
	private DateTime $selectedDate;
	/** @var array<string,mixed> */
	private array $configuration;

	public function __construct(
		private readonly BookingCalendarProvider $bookingCalendarProvider,
		private readonly Latte                   $latte,
		private readonly Serializer              $serializer,
		private readonly Arena                   $arena,
		public int                               $year = 0,
		public int                               $month = 0,
		public int                               $day = 0,
		string                                   $selectedDate = 'now',
		?BookingType                             $type = null,
		?BookingSubtype                          $subType = null,
	) {
		if (!isset($type)) {
			/** @noinspection CallableParameterUseCaseInTypeContextInspection */
			$type = BookingType::query()->first();
		}
		$this->type = $type;
		if (!isset($subType)) {
			$subType = first($type->activeSubtypes);
		}
		$this->subType = $subType;
		if ($this->year === 0) {
			$this->year = (int)date('Y');
		}
		if ($this->month === 0) {
			$this->month = (int)date('m');
		}
		if ($this->day === 0) {
			$this->day = (int)date('d');
		}
		$this->configuration = [
			'year'         => $this->year,
			'month'        => $this->month,
			'day'          => $this->day,
			'selectedDate' => $selectedDate,
			'type'         => $this->type,
			'subType'      => $this->subType?->id,
		];
		$this->selectedDate = new DateTime($this->day === 0 ? 'now' : $selectedDate);
	}

	/**
	 * @return string
	 * @throws Throwable
	 */
	public function render(): string {
		$params = new CalendarParameters();

		$params->arena = $this->arena;
		$params->type = $this->type;
		$params->subType = $this->subType;
		$params->slotLimit = $this->type->slotLimit;
		$params->year = $this->year;
		$params->month = $this->month;
		$params->day = $this->day;
		$params->days = self::DAYS;
		$params->months = self::MONTHS;
		$params->selected = $this->selectedDate->format('Y-m-d');

		// Initialize necessary variables
		$now = new DateTimeImmutable();
		$today = new DateTimeImmutable('00:00:00');
		$availabilityStart = clone $today;
		$currDate = new DateTimeImmutable($this->year . '-' . $this->month . '-1');
		$monthEnd = new DateTimeImmutable($this->year . '-' . $this->month . '-1 + 1 months');
		$oneDay = new DateInterval('P1D');

		while ($currDate < $monthEnd) {
			$week = (int)$currDate->format('W');
			$day = (int)$currDate->format('N');

			// Initialize empty week if it doesn't exist
			if (!isset($params->weeks[$week])) {
				$params->weeks[$week] = [
					1 => null,
					2 => null,
					3 => null,
					4 => null,
					5 => null,
					6 => null,
					7 => null,
				];
			}

			$date = $currDate->format('Y-m-d');
			$classes = [];

			$full = false;
			$available = false;
			$onCallOnly = false;
			$empty = true;
			// Check availability
			if ($currDate >= $availabilityStart && $this->bookingCalendarProvider->isDateOpen($currDate, $this->type)) {
				$query = new BookingTimeSlotsQuery($this->type, $currDate);
				$times = $query->now($now)->includeClosedTimes()->get();

				if ($date === $this->selectedDate->format('Y-m-d')) {
					$params->times = $times;
				}

				$onCallOnly = array_all(
					$times,
					static fn(BookingTimeStatus $time) => $time->status === TimeStatus::ON_CALL
				);
				$empty = !array_any(
					$times,
					static fn(BookingTimeStatus $time) => $time->status === TimeStatus::PARTIALLY_FILLED || $time->status === TimeStatus::FILLED
				);
				$full = !array_any(
					$times,
					static fn(BookingTimeStatus $time) => $time->status === TimeStatus::AVAILABLE || $time->status === TimeStatus::PARTIALLY_FILLED,
				);
				$available = array_any(
					$times,
					static fn(BookingTimeStatus $time) => $time->status !== TimeStatus::FILLED && $time->status !== TimeStatus::CLOSED,
				);
			}

			// Add classes
			if ($available) {
				$classes[] = 'daysFree';
			}
			else {
				$classes[] = 'disabled';
			}

			if ($onCallOnly) {
				$classes[] = 'onCallOnly';
			}

			if (!$empty) {
				$classes[] = 'notEmpty';
			}

			if ($full) {
				$classes[] = 'full';
			}

			$params->weeks[$week][$day] = new CalendarDay(
				$date,
				(int)$currDate->format('d'),
				$currDate->format("m, Y, 'Y-m-d'"),
				$classes,
				$today->format('Y-m-d') === $date,
				$available,
			);

			// Increment current date by one day
			$currDate = $currDate->add($oneDay);
		}

		// Generate calendar
		$nextMonth = new DateTimeImmutable($this->year . '-' . $this->month . '-1 +1 months');
		$previousMonth = new DateTimeImmutable($this->year . '-' . $this->month . '-1 -1 months');

		uksort($params->times, static function (string $time1, string $time2) {
			return strtotime($time1) - strtotime($time2);
		});

		$params->previousMonthDisabled = $this->month === (int)$today->format('m');
		$params->todayDay = $today->format("d");
		$params->todayMonth = $today->format("m");
		$params->todayYear = $today->format("Y");
		$params->nextMonth = $nextMonth->format('m');
		$params->nextMonthYear = $nextMonth->format('Y');
		$params->previousMonth = $previousMonth->format('m');
		$params->previousMonthYear = $previousMonth->format('Y');
		$params->configuration = str_replace(
			'"',
			'\"',
			$this->serializer->serialize(
				$this->configuration,
				'json',
			)
		);

		return $this->latte->viewToString('components/calendar', $params);
	}
}