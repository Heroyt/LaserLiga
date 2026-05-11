<?php
declare(strict_types=1);

namespace App\Models\Booking\DataObjects;

class CalendarDay
{

	/**
	 * @param string[]  $classes
	 */
	public function __construct(
		public string $value,
		public int    $label,
		public string $arg,
		public array  $classes = [],
		public bool   $today = false,
		public bool   $available = false,
	) {
	}

}