<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Ranking;

use App\Models\DataObjects\Player\Player;

class PlayerRankDiffResult
{


	public float $result;
	public float $expectedResult;
	public float $marginOfVictory;

	public float $diff;

	public float $ratingDiff;

	public function __construct(
		public PlayerType $type,
		public RankingPlayer $player,
		public float $normalizedSkill,
	) {
	}
}