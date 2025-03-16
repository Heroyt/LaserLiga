<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Player;

use App\Helpers\DateTimeStringable;

class PlayerDateRank
{
	public DateTimeStringable $date;
	public ?int               $position;
	public string             $position_text;
}