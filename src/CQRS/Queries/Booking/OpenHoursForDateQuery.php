<?php
declare(strict_types=1);

namespace App\CQRS\Queries\Booking;

use App\CQRS\Queries\WithCacheQuery;
use App\Models\Arena;
use App\Models\Booking\BookingType;
use App\Models\Booking\OpenHours;
use App\Models\Booking\SpecialOpenHours;
use App\Models\Booking\TimeInterval;
use DateTimeInterface;
use Lsr\CQRS\QueryInterface;
use Throwable;

final class OpenHoursForDateQuery implements QueryInterface
{
	use WithCacheQuery;

	private bool         $includeSpecialHours = true;
	private bool         $includeNormalHours  = true;
	private ?BookingType $type                = null;

	public function __construct(
		private readonly Arena             $arena,
		private readonly DateTimeInterface $date,
	) {
	}

	public function type(BookingType $type): OpenHoursForDateQuery {
		$this->type = $type;
		return $this;
	}

	public function includeSpecialHours(bool $include = true): OpenHoursForDateQuery {
		$this->includeSpecialHours = $include;
		return $this;
	}

	public function includeNormalHours(bool $include = true): OpenHoursForDateQuery {
		$this->includeNormalHours = $include;
		return $this;
	}

	/**
	 * @return OpenHours[]
	 */
	private function getNormalOpenHours(): array {
		if (!$this->includeNormalHours) {
			return [];
		}

		$day = (int)$this->date->format('N'); // 1 (Monday) to 7 (Sunday)

		$cacheTags = [
			'booking',
			'open_hours',
			'open_hours/' . $this->arena->id,
			'open_hours/' . $this->date->format('Y-m-d'),
		];

		if ($this->type !== null) {
			$cacheTags[] = 'open_hours/type/' . $this->type->id;

			// Fetch open hours for the specific type and day.
			$typeHours = OpenHours::queryForArenaAndType($this->arena, $this->type)
			                      ->where('[day] = %i', $day)
			                      ->cacheTags(...$cacheTags)
			                      ->get($this->cache);
			// If open hours exist for the given type and day, return them.
			if (!empty($typeHours)) {
				return $typeHours;
			}
		}

		// Fallback to the arena's open hours if no type-specific hours are found.
		return OpenHours::queryForArenaAndType($this->arena)
		                ->where('[day] = %i', $day)
		                ->cacheTags(...$cacheTags)
		                ->get($this->cache);
	}

	/**
	 * @return TimeInterval[]
	 */
	public function get(): array {
		if ($this->cache) {
			try {
				return $this->cacheService->load(
					'booking.open_hours.' . $this->type->id . '.' . $this->date->format('Y-m-d'),
					fn() => $this->getTimes(),
					[
						$this->cacheService::Tags   => [
							'booking',
							'open_hours',
							'open_hours/' . $this->arena->id,
							'open_hours/type/' . $this->type->id,
							'open_hours/' . $this->date->format('Y-m-d'),
						],
						$this->cacheService::Expire => '7 days',
					]
				);
			} catch (Throwable) {
				// Ignore and fall back to the non-cached version.
			}
		}
		return $this->getTimes();
	}

	/**
	 * @return TimeInterval[]
	 */
	private function getTimes(): array {
		$specialHours = $this->getSpecialOpenHours();

		if (array_any($specialHours, static fn(SpecialOpenHours $hour) => $hour->closed)) {
			return []; // Closed for the entire day.
		}

		if (!empty($specialHours)) {
			$times = $this->extractTimes($specialHours);
			$times = $this->mergeTimes($times);
			$this->sortTimeIntervals($times);
			return $times;
		}

		$hours = $this->getNormalOpenHours();
		if (empty($hours)) {
			return []; // No open hours for the given type and date.
		}
		$times = $this->extractTimes($hours);
		$times = $this->mergeTimes($times);
		$this->sortTimeIntervals($times);
		return $times;
	}

	/**
	 * @return SpecialOpenHours[]
	 */
	private function getSpecialOpenHours(): array {
		if (!$this->includeSpecialHours) {
			return [];
		}

		$cacheTags = [
			'booking',
			'open_hours',
			'open_hours/' . $this->arena->id,
			'open_hours/' . $this->date->format('Y-m-d'),
		];

		if ($this->type !== null) {
			$cacheTags[] = 'open_hours/type/' . $this->type->id;

			// Fetch open hours for the specific type and day.
			$typeHours = SpecialOpenHours::queryForArenaAndType($this->arena, $this->type)
			                             ->where('[date] = %d', $this->date)
			                             ->cacheTags(...$cacheTags)
			                             ->get($this->cache);
			// If open hours exist for the given type and day, return them.
			if (!empty($typeHours)) {
				return $typeHours;
			}
		}

		// Fallback to the arena's open hours if no type-specific hours are found.
		return SpecialOpenHours::queryForArenaAndType($this->arena)
		                       ->where('[date] = %d', $this->date)
		                       ->cacheTags(...$cacheTags)
		                       ->get($this->cache);
	}

	/**
	 * @param OpenHours[]|SpecialOpenHours[] $hours
	 *
	 * @return TimeInterval[]
	 */
	private function extractTimes(array $hours): array {
		$times = [];
		foreach ($hours as $hour) {
			if (!$hour->onCallTimes->isEmpty()) {
				$times[] = $hour->onCallTimes;
			}
			if (!$hour->times->isEmpty()) {
				$times[] = $hour->times;
			}
		}
		return $times;
	}

	/**
	 * Merges overlapping or contiguous time intervals.
	 *
	 * Normal TimeInterval overrides OnCallTimeInterval.
	 *
	 * @pre $intervals must not be empty.
	 * @pre $intervals must be sorted by start time.
	 *
	 * @param TimeInterval[] $intervals
	 *
	 * @return TimeInterval[]
	 */
	private function mergeTimes(array $intervals): array {
		// Intervals must not be empty.
		assert(array_all($intervals, static fn(TimeInterval $i): bool => !$i->isEmpty()));

		$newIntervals = [];
		$lastInterval = null;
		$addedLastInterval = false;
		foreach ($intervals as $interval) {
			if ($lastInterval === null) {
				$lastInterval = $interval;
				$addedLastInterval = false;
				continue;
			}

			// If intervals don't overlap, we just add the first one as is.
			if (!$lastInterval->overlaps($interval)) {
				$newIntervals[] = $lastInterval;
				$lastInterval = $interval;
				$addedLastInterval = false;
				continue;
			}

			$merged = $lastInterval->combine($interval);
			// Should always return at least one interval because the overlap is guaranteed by the precondition.
			assert(!empty($merged));
			foreach ($merged as $int) {
				$newIntervals[] = $int;
				$lastInterval = $int;
				$addedLastInterval = true;
			}
		}
		// Add the last interval if it exists.
		if ($lastInterval !== null && !$addedLastInterval) {
			$newIntervals[] = $lastInterval;
		}
		return $newIntervals;
	}

	/**
	 * @param TimeInterval[] $intervals
	 */
	private function sortTimeIntervals(array &$intervals): void {
		usort(
			$intervals,
			static fn(TimeInterval $a, TimeInterval $b): int => $a->start === null || $b->start === null ? 0 :
				$a->start <=> $b->start
		);
	}
}