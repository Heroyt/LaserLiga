<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Player\Leaderboard;

use DateTimeInterface;

final class PlayerRatingDeltaRow
{
	public int               $userId;
	public DateTimeInterface $date;
	public float             $difference;
}
