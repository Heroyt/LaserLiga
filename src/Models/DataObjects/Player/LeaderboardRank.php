<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Player;

readonly class LeaderboardRank
{
	public function __construct(
		public string $rank,
		public int $difference,
	){}
}