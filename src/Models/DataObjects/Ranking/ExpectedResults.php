<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Ranking;

class ExpectedResults
{

	/** @var PlayerRankDiffResult[] */
	public array $players = [];

	public float $normalizedSkill = 0.0;

	public function __construct(
		public string $user,
		public int    $currentRank,
		public float  $teamRank,
		public float  $teamSkill,
		public float  $enemiesRank,
		public float  $enemiesSkill,
		public float  $Q,
	) {
	}

	public function addPlayer(PlayerRankDiffResult $player): void {
		$this->players[] = $player;
	}

}