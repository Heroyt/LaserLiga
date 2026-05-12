<?php
declare(strict_types=1);

namespace App\Services\Player\Ranking;

use App\Models\DataObjects\Ranking\Calculation\RankDeltaResult;
use App\Models\DataObjects\Ranking\PlayerRankDiffResult;
use App\Models\DataObjects\Ranking\PlayerType;
use App\Models\DataObjects\Ranking\RankingPlayer;
use DateTimeInterface;

final readonly class RankDeltaCalculator
{

	/** @var int Difference of RATING_RATIO_CONSTANT between players should mean that one player is 10 times more likely to win */
	public const int RATING_RATIO_CONSTANT = 400;

	/** @var int How strongly a result should affect the rating change */
	public const int K_FACTOR = 10;

	/** @var int Padding applied to the worst player's skill */
	public const int MIN_PLAYER_PADDING = 50;

	/** @var int Padding applied to the best player's skill */
	public const int MAX_PLAYER_PADDING = 0;

	/** @var float Weight applied to player skill comparison if both players are teammates */
	public const float TEAMMATE_WEIGHT = 0.5;

	/**
	 * @param RankingPlayer[] $teammates
	 * @param RankingPlayer[] $enemies
	 */
	public function calculateForPlayer(
		int               $skill,
		int|float         $minSkill,
		int|float         $maxSkill,
		array             $teammates,
		array             $enemies,
		string            $code,
		int               $userId,
		string            $userName,
		DateTimeInterface $date,
		int               $currentDateRank,
	): RankDeltaResult {
		$ratingDiff = 0.0;
		$count = 0;

		$minSkill -= self::MIN_PLAYER_PADDING;
		$maxSkill += self::MAX_PLAYER_PADDING;

		$teamRank = $this->getTeamRank($teammates);
		$enemiesRank = $this->getTeamRank($enemies);

		$teamSkill = $this->getTeamSkill($teammates);
		$enemiesSkill = $this->getTeamSkill($enemies);

		$Q = 2.2 / ((($teamSkill > $enemiesSkill ? $teamRank - $enemiesRank : $enemiesRank - $teamRank) * 0.001) + 2.2);

		$expectedResults = [
			'user'         => $userName,
			'currentRank'  => $currentDateRank,
			'teamRank'     => $teamRank,
			'teamSkill'    => $teamSkill,
			'enemiesRank'  => $enemiesRank,
			'enemiesSkill' => $enemiesSkill,
			'Q'            => $Q,
			'players'      => [],
		];

		$normalizedSkill = null;
		if ($maxSkill > 0 && $maxSkill !== $minSkill) {
			$normalizedSkill = ($skill - $minSkill) / ($maxSkill - $minSkill);
			foreach ($enemies as $enemy) {
				$result = new PlayerRankDiffResult(PlayerType::ENEMY, $enemy, $normalizedSkill);
				$this->calculateRankingResult($result, $skill, $currentDateRank, $minSkill, $maxSkill, $Q);
				$ratingDiff += $result->ratingDiff;
				$count++;
				$expectedResults['players'][] = $result;
			}
			foreach ($teammates as $teammate) {
				if ($teammate->id_user === $userId) {
					continue;
				}
				$result = new PlayerRankDiffResult(PlayerType::TEAMMATE, $teammate, $normalizedSkill);
				$this->calculateRankingResult($result, $skill, $currentDateRank, $minSkill, $maxSkill, $Q);
				$ratingDiff += $result->ratingDiff;
				$count++;
				$expectedResults['players'][] = $result;
			}
		}

		if ($count > 0) {
			$ratingDiff *= self::K_FACTOR / $count;
		}

		return new RankDeltaResult(
			$code,
			$userId,
			$date,
			max(min($ratingDiff, 50.0), -50.0),
			$normalizedSkill,
			$minSkill,
			$maxSkill,
			$expectedResults,
		);
	}

	/**
	 * @param RankingPlayer[] $players
	 */
	private function getTeamRank(array $players): float {
		$count = count($players);
		if ($count === 0) {
			return 0.0;
		}
		return array_reduce($players, static fn($a, $b) => $a + ($b->rank ?? $b->skill), 0) / $count;
	}

	/**
	 * @param RankingPlayer[] $players
	 */
	private function getTeamSkill(array $players): float {
		$count = count($players);
		if ($count === 0) {
			return 0.0;
		}
		return array_reduce($players, static fn($a, $b) => $a + $b->skill, 0) / $count;
	}

	private function calculateRankingResult(PlayerRankDiffResult $result, int $skill, int $currentDateRank, int|float $minSkill, int|float $maxSkill, float $Q): void {
		$diff = ($result->player->rank ?? $result->player->skill) - $currentDateRank;
		$normalizedEnemySkill = ($result->player->skill - $minSkill) / ($maxSkill - $minSkill);

		$result->expectedResult = 1 / (1 + 10 ** ($diff / self::RATING_RATIO_CONSTANT));
		$result->marginOfVictory = log(abs($skill - $result->player->skill) + 1) * $Q;

		$result->result = 1 / (1 + 100 ** ($normalizedEnemySkill - $result->normalizedSkill));
		$result->ratingDiff = ($result->result - $result->expectedResult) * $result->marginOfVictory;
		if ($result->type === PlayerType::TEAMMATE) {
			$result->ratingDiff *= self::TEAMMATE_WEIGHT;
		}
	}

}
