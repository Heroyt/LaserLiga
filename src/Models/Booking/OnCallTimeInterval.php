<?php
declare(strict_types=1);

namespace App\Models\Booking;

use Dibi\Row;

class OnCallTimeInterval extends TimeInterval
{

	public static function parseRow(Row $row): static {
		return new self(
			$row->call_start,
			$row->call_end,
		);
	}

	public function addQueryData(array &$data): void {
		$data['call_start'] = $this->start;
		$data['call_end'] = $this->end;
	}

}