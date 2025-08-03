<?php
declare(strict_types=1);

namespace App\CQRS\Queries\Booking;

use App\CQRS\Queries\WithCacheQuery;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\BookingType;
use App\Models\Booking\DataObjects\BookingTimeStatus;
use App\Models\Booking\Enums\TimeStatus;
use App\Models\Booking\OnCallTimeInterval;
use App\Models\Booking\TimeInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\CQRS\QueryInterface;
use RuntimeException;
use Throwable;

class BookingTimeSlotsQuery implements QueryInterface
{
	use WithCacheQuery;

	private bool $cacheSlots = true;

	private bool            $includeBookings = false;
	private ?BookingSubType $subType         = null;

	private ?DateTimeImmutable $now = null;

	private bool $includePast = false;

	private bool $includeClosedTimes = false;

	public function __construct(
		private readonly BookingType       $type,
		private readonly DateTimeInterface $date,
	) {
	}

	public function includePast(bool $include = true): static {
		$this->includePast = $include;
		return $this;
	}

	public function now(DateTimeInterface $date): BookingTimeSlotsQuery {
		// Make sure the date is immutable
		if (!($date instanceof DateTimeImmutable)) {
			$date = DateTimeImmutable::createFromInterface($date);
		}
		$this->now = $date;
		return $this;
	}

	public function includeBookings(bool $include = true): static {
		$this->includeBookings = $include;
		return $this;
	}

	public function includeClosedTimes(bool $include = true): static {
		$this->includeClosedTimes = $include;
		return $this;
	}

	public function subType(?BookingSubType $subType): static {
		$this->subType = $subType;
		return $this;
	}

	/**
	 * Disable caching for the final generation, but not for everything else.
	 */
	public function noCacheSlots(): static {
		$this->cacheSlots = false;
		return $this;
	}

	/**
	 * @return Booking[]
	 */
	private function getBookings(): array {
		// Fetch bookings for the given type and date
		return Booking::queryActive()
		              ->where(
			              'DATE([datetime]) = %d AND [id_type] = %i',
			              $this->date,
			              $this->type->id,
		              )
		              ->orderBy('datetime')
		              ->get($this->cache);
	}

	/**
	 * @return array<string, BookingTimeStatus>
	 */
	public function get(): array {
		if ($this->cacheSlots && $this->cache && $this->now === null) {
			try {
				return $this->cacheService->load(
					$this->cacheKey(),
					fn() => $this->generateTimes(),
					[
						$this->cacheService::Expire => '1 day',
						$this->cacheService::Tags   => [
							'booking',
							'times',
							'times/' . $this->type->id,
							'times/' . $this->date->format('Y-m-d'),
						],
					]
				);
			} catch (Throwable) {
				// Ignore and fallback to no-cache mode
			}
		}
		return $this->generateTimes();
	}

	/**
	 * @return string
	 */
	public function cacheKey(): string {
		$key = 'booking.times';
		if ($this->subType !== null) {
			$key .= '.' . $this->subType->id;
		}
		$key .= '.' . $this->type->id . '.' . $this->date->format('Y-m-d');
		if ($this->includeBookings) {
			$key .= '.bookings';
		}
		if ($this->includePast) {
			$key .= '.past';
		}
		if ($this->includeClosedTimes) {
			$key .= '.closed';
		}
			return $key;
	}

	/**
	 * @return array<string, BookingTimeStatus>
	 */
	private function generateTimes(): array {
		$timesQuery = new OpenHoursForDateQuery($this->type->arena, $this->date)
			->type($this->type)
			->includeSpecialHours()
			->includeNormalHours();
		if (!$this->cache) {
			$timesQuery->noCache();
		}
		$times = $timesQuery->get();

		$closed = false;
		if (empty($times)) {
			if (!$this->includePast) {
				return [];
			}
			// If no times are found, we still want to generate slots for the date.
			$timesQuery->includeSpecialHours(false);
			$times = $timesQuery->get();
			$closed = true;
		}

		$slots = [];

		$globalStart = null;

		// Generate slots for each time interval
		foreach ($times as $time) {
			// Store the first start time globally to use it for all intervals.
			$globalStart ??= DateTimeImmutable::createFromFormat(
				'Y-m-d H:i',
				$this->date->format('Y-m-d') . $time->start->format(' H:i'),
			);
			if ($globalStart === false) {
				throw new RuntimeException('Invalid date format for booking time slots.');
			}

			// Set date to be the same (we only care about the time).
			$time->setDate($globalStart);

			// Subtype can enable on-call times to work as normal times.
			$onCall = !($this->subType->unlockOnCall ?? false) && $time instanceof OnCallTimeInterval;
			foreach ($this->makeSlotsForInterval($time, $globalStart) as $slot) {
				$isPast = $this->includePast && $this->now !== null && $slot < $this->now;
				$slots[$slot->format('Y-m-d H:i')] = new BookingTimeStatus(
					$slot->format('H:i'),
					$isPast || $closed ? TimeStatus::CLOSED : ($onCall ? TimeStatus::ON_CALL : TimeStatus::AVAILABLE),
					$this->subType->slotMax ?? $this->type->slotLimit,
				);
			}
		}

		// Check bookings for generated slots
		$bookings = $this->getBookings();
		foreach ($bookings as $booking) {
			foreach ($booking->filledSlots as $slot => $filled) {
				if (!$filled || !isset($slots[$slot])) {
					continue;
				}

				$slots[$slot]->availableSpots -= $booking->playerCount;
				if ($this->includeBookings) {
					$slots[$slot]->bookings[] = $booking;
				}

				// Slot should lock if it is explicitly locked by the booking, the player count exceeds the slot limit, or if the subtype forces the slot fill.
				$locked = $booking->locked || $slots[$slot]->availableSpots < 1 || ($this->subType->slotFill ?? false);
				if ($locked) {
					$slots[$slot]->status = TimeStatus::FILLED;
					$slots[$slot]->availableSpots = 0;
				}
				elseif ($slots[$slot]->availableSpots < $this->type->slotLimit && !$slots[$slot]->status->isFinalStatus(
					)) {
					$slots[$slot]->status = TimeStatus::PARTIALLY_FILLED;
				}
			}
		}

		return $slots;
	}

	/**
	 * @return iterable<DateTimeImmutable>
	 */
	private function makeSlotsForInterval(TimeInterval $interval, DateTimeImmutable $globalStart): iterable {
		$slotLength = $this->type->getLength();

		// Move global start until it is aligned with the interval start.
		// This is to prevent unperfect slot alignments.
		// E.g. Global start is 10:00, the slot length is 30 minutes, and the interval starts at 10:15.
		// This will make sure the first slot in this interval starts at 10:30.
		$start = $globalStart;
		while ($start < $interval->start) {
			$start = $start->add($slotLength);
		}

		$end = DateTimeImmutable::createFromFormat(
			'Y-m-d H:i',
			$this->date->format('Y-m-d') . $interval->end->format(' H:i'),
		);
		if ($end === false) {
			throw new RuntimeException('Invalid date format for booking time slots.');
		}
		while ($start < $end) {
			// Generate a slot only if it is not in the past.
			if ($this->includePast || $this->now === null || $start > $this->now) {
				yield $start;
			}
			$start = $start->add($slotLength);
		}
	}
}