<?php
declare(strict_types=1);

namespace App\Services\Booking;

use App\CQRS\Queries\Booking\OpenHoursForDateQuery;
use App\Models\Booking\BookingType;
use DateTimeInterface;
use Lsr\Core\App;

class BookingCalendarProvider
{

	/** @var array<string, bool>[] */
	private array $isDateOpen = [];


	/**
	 * Check if a specific date is open for bookings of a given type.
	 *
	 * @param DateTimeInterface $date
	 * @param BookingType       $type
	 *
	 * @return bool
	 */
	public function isDateOpen(DateTimeInterface $date, BookingType $type, bool $cache = true): bool {
		$formatted = $date->format('Y-m-d');
		if (!$cache || !isset($this->isDateOpen[$type->id][$formatted])) {
			$query = new OpenHoursForDateQuery($type->arena, $date);
			$query->type($type)
			      ->includeNormalHours()
			      ->includeSpecialHours();

			if (!$cache) {
				$query->noCache();
			}

			$this->isDateOpen[$type->id][$formatted] = !empty($query->get());
		}
		return $this->isDateOpen[$type->id][$formatted];
	}

}