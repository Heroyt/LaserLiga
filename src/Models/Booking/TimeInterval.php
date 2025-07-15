<?php
declare(strict_types=1);

namespace App\Models\Booking;

use DateTimeInterface;
use Dibi\Row;
use Lsr\Orm\Interfaces\InsertExtendInterface;

class TimeInterval implements InsertExtendInterface
{

	public function __construct(
		public DateTImeInterface $start,
		public DateTimeInterface $end,
	){}


	public static function parseRow(Row $row): ?static {
		return new self(
			$row->start,
			$row->end,
		);
	}

	public function addQueryData(array &$data): void {
		$data['start'] = $this->start;
		$data['end'] = $this->end;
	}
}