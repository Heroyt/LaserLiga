<?php
declare(strict_types=1);

namespace App\Services\Booking;

use App\CQRS\Queries\Booking\BookingTimeSlotsQuery;
use App\CQRS\Queries\Booking\OpenHoursForDateQuery;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\BookingType;
use App\Models\Booking\Enums\TimeStatus;
use DateTimeImmutable;
use DateTimeInterface;

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

	public function isSlotAvailable(
		DateTimeInterface $slot,
		BookingType $type,
		?BookingSubType $subType = null,
		?int $playerCount = null,
		bool $allowAll = false,
		bool $allowOverbooking = false,
	): bool {
		// Check if the date is open for bookings
		if (!$this->isDateOpen($slot, $type)) {
			return false;
		}

		$query = new BookingTimeSlotsQuery($type, $slot);
		$query->subtype($subType);

		$slots = $query->get();
		$time = $slot->format('Y-m-d H:i');
		if (!isset($slots[$time])) {
			return false; // Slot does not exist
		}

		if ($slots[$time]->status === TimeStatus::FILLED) {
			return false;
		}

		// Arena staff can book closed or on-call slots
		if (!$allowAll && ($slots[$time]->status === TimeStatus::CLOSED || $slots[$time]->status === TimeStatus::ON_CALL)) {
			return false;
		}

		if ($playerCount !== null && !$allowOverbooking) {
			return $slots[$time]->availableSpots >= $playerCount;
		}

		return true;
	}

	/**
	 * Gets the closest valid slot time for a given datetime and booking type.
	 */
	public function getSlotTime(DateTimeInterface $slot, BookingType $type, ?BookingSubType $subType = null): DateTimeInterface {
		$query = new BookingTimeSlotsQuery($type, $slot);
		$query->subtype($subType);
		$slots = $query->get();

		$time = $slot->format('Y-m-d H:i');
		if (isset($slots[$time])) {
			return $slot; // Original time is already valid
		}

		$time = $slot->format('H:i');

		// Find closest slot
		$previousSlot = null;
		foreach ($slots as $date => $status) {
			if ($status->time > $time) {
				break; // Slots are sorted, so we can stop here
			}
			$previousSlot = $date;
		}

		if ($previousSlot !== null) {
			return DateTimeImmutable::createFromFormat('Y-m-d H:i', $previousSlot);
		}
		// If no previous slot found, return the original slot
		return $slot;
	}

}