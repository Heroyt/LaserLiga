<?php
declare(strict_types=1);

namespace App\Models\Booking;

use DateTimeInterface;
use Dibi\Row;
use Lsr\Orm\Interfaces\InsertExtendInterface;

class TimeInterval implements InsertExtendInterface
{

	public function __construct(
		public ?DateTimeInterface $start,
		public ?DateTimeInterface $end,
	){}


	public static function parseRow(Row $row): ?static {
		return new self(
			self::toDateTimeInterface($row->start),
			self::toDateTimeInterface($row->end),
		);
	}

	protected static function toDateTimeInterface(null|DateTimeInterface|\DateInterval $time) : ?DateTimeInterface {
		if ($time === null) {
			return null;
		}
		if ($time instanceof DateTimeInterface) {
			return $time;
		}
		return \DateTimeImmutable::createFromFormat('H:i:s', $time->format('%H:%I:%S'));
	}

	public function addQueryData(array &$data): void {
		$data['start'] = $this->start;
		$data['end'] = $this->end;
	}

	/**
	 * Check if both start and end times are set.
	 */
	public function isEmpty(): bool {
		return $this->start === null || $this->end === null;
	}

	/**
	 * Check if another TimeInterval is completely contained within this interval.
	 */
	public function contains(TimeInterval $interval, bool $includeBounds = true): bool {
		if ($this->isEmpty() || $interval->isEmpty()) {
			return false;
		}
		if ($includeBounds) {
			return $this->start <= $interval->start && $this->end >= $interval->end;
		}
		return $this->start < $interval->start && $this->end > $interval->end;
	}

	/**
	 * Checks if this TimeInterval overlaps with another TimeInterval (at least partially).
	 */
	public function overlaps(TimeInterval $interval, bool $includeBounds = true): bool {
		if ($this->isEmpty() || $interval->isEmpty()) {
			return false;
		}
		return $this->isInInterval($interval->start, $includeBounds) || $this->isInInterval($interval->end, $includeBounds);
	}

	public function isInInterval(DateTimeInterface $date, bool $includeBounds = true): bool {
		if ($this->isEmpty()) {
			return false;
		}
		if ($includeBounds) {
			return $date >= $this->start && $date <= $this->end;
		}
		return $date > $this->start && $date < $this->end;
	}

	/**
	 * Combine two time intervals. This method will merge and or split intervals into distinct time frames.
	 *
	 * If intervals don't overlap, they will be returned as separate intervals.
	 * If intervals are of the same type, they can be merged into one interval.
	 * If intervals are of different types, they must be split into multiple intervals based on their types.
	 * Normal TimeInterval overrides OnCallTimeInterval.
	 *
	 * @return TimeInterval[] Intervals that are distinct and do not overlap.
	 */
	public function combine(TimeInterval $other): array {
		if ($this->isEmpty() && $other->isEmpty()) {
			return [];
		}
		if ($this->isEmpty()) {
			return [$other];
		}
		if ($other->isEmpty()) {
			return [$this];
		}

		// No overlap, return both intervals as separate.
		if (!$this->overlaps($other)) {
			return [$this, $other];
		}

		// Same type intervals can be merged. Otherwise, intervals must be split.
		$isSameType = $this::class === $other::class;
		if ($isSameType) {
			$type = $this::class;
			// Merge intervals of the same type.
			return [
				new $type(
					min($this->start, $other->start),
					max($this->end, $other->end)
				),
			];
		}

		$onCallInterval = $this instanceof OnCallTimeInterval ? $this : $other;
		$normalInterval = $this instanceof OnCallTimeInterval ? $other : $this;

		// Normal TimeInterval overrides OnCallTimeInterval.
		if ($normalInterval->contains($onCallInterval)) {
			// On call interval is completely contained in the normal interval, so we return normal interval.
			return [$normalInterval];
		}

		// Normal interval starts first.
		if ($normalInterval->start < $onCallInterval->start) {
			return [
				// We can push the normal interval, because it overrides the on-call interval.
				$normalInterval,
				// On call interval is shortened to the end of the normal interval.
				new OnCallTimeInterval(
					$normalInterval->end,
					$onCallInterval->end,
				)];
		}

		// On call interval starts first.
		$intervals = [];

		// If the start times would equal, we just omit the on call interval from the start.
		if ($onCallInterval->start < $normalInterval->start) {
			// We shorten the on call interval to the start of the normal interval.
			$intervals[] = new OnCallTimeInterval(
				$onCallInterval->start,
				$normalInterval->start,
			);
		}

		// We can push the normal interval, because it overrides the on-call interval.
		$intervals[] = $normalInterval;

		// On call interval is shortened by the end of the normal interval.
		if ($onCallInterval->end > $normalInterval->end) {
			$intervals[] = new OnCallTimeInterval(
				$normalInterval->end,
				$onCallInterval->end,
			);
		}

		return $intervals;
	}

	public function setDate(DateTimeInterface $date): void {
		if ($this->start !== null) {
			assert($this->start instanceof \DateTimeImmutable);
			$this->start = $this->start->setDate(
				(int) $date->format('Y'),
				(int) $date->format('m'),
				(int) $date->format('d')
			);
		}
		if ($this->end !== null) {
			assert($this->end instanceof \DateTimeImmutable);
			$this->end = $this->end->setDate(
				(int) $date->format('Y'),
				(int) $date->format('m'),
				(int) $date->format('d')
			);
		}
	}

}