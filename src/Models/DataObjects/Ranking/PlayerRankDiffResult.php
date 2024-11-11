<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Ranking;

use App\Models\DataObjects\Player\Player;

class PlayerRankDiffResult
{


	public float $result = 0.0;
	public float $expectedResult = 0.0;
	public float $marginOfVictory = 0.0;

	public float $diff = 0.0;

	public float $ratingDiff = 0.0;

	public function __construct(
		public PlayerType $type,
		public RankingPlayer $player,
		public float $normalizedSkill,
	) {
	}
}