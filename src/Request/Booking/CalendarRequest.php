<?php
declare(strict_types=1);

namespace App\Request\Booking;

class CalendarRequest
{

	public int $year = 0;
	public int $month = 0;
	public int $day = 0;
	public string $selectedDate = 'now';
	/** @var array{id?:numeric}|int  */
	public array|int $type = 0;
	/** @var array{id?:numeric}|int|null  */
	public array|int|null $subType = null;

	public function getTypeId() : int {
		return is_array($this->type) && isset($this->type['id']) ? (int)$this->type['id'] : (int)$this->type;
	}

	public function getSubTypeId() : ?int {
		if (empty($this->subType)) {
			return null;
		}
		return is_array($this->subType) && isset($this->subType['id']) ? (int)$this->subType['id'] : (int)$this->subType;
	}

}