<?php
declare(strict_types=1);

namespace App\Services\Player\Ranking;

use App\Models\DataObjects\Ranking\Calculation\GameCalculationInput;
use App\Models\DataObjects\Ranking\Calculation\PlayerCalculationInput;
use App\Models\DataObjects\Ranking\Calculation\RankDeltaResult;
use App\Models\DataObjects\Ranking\RankingPlayer;
use Symfony\Component\Serializer\Serializer;

final readonly class RankRecalculationService
{

	public function __construct(
		private SqlGameCalculationInputProvider   $sqlInputProvider,
		private ModelGameCalculationInputProvider $modelInputProvider,
		private RankDeltaCalculator              $rankDeltaCalculator,
		private RatingRepository                 $ratingRepository,
		private Serializer                       $serializer,
	) {
	}

	public function recalculateGame(string $code, bool $useSqlInput = false): RecalculationSummary {
		$input = ($useSqlInput ? $this->sqlInputProvider : $this->modelInputProvider)->getByCode($code);
		if (!isset($input)) {
			return new RecalculationSummary(0, 0, []);
		}

		return $this->recalculateInput($input);
	}

	/**
	 * @param array<int, int>|null $rankState userId => rank before this game
	 */
	public function recalculateInput(GameCalculationInput $input, ?array &$rankState = null): RecalculationSummary {
		if (!$input->rankable) {
			return new RecalculationSummary(0, 0, []);
		}

		$rankState ??= [];
		$this->ensureRankState($input, $rankState);
		$rankDeltas = $this->calculateDeltas($input, $rankState);
		$this->persistGameDeltas($input, $rankDeltas);

		$affectedUserIds = [];
		foreach ($rankDeltas as $rankDelta) {
			$rankState[$rankDelta->userId] = max(0, (int)round(($rankState[$rankDelta->userId] ?? 100) + $rankDelta->difference));
			$affectedUserIds[$rankDelta->userId] = $rankDelta->userId;
		}

		$this->ratingRepository->recalculateUserRanks(array_values($affectedUserIds));

		return new RecalculationSummary(
			1,
			count($rankDeltas),
			array_values($affectedUserIds),
		);
	}

	/**
	 * @param array<int, int> $rankState
	 */
	private function ensureRankState(GameCalculationInput $input, array &$rankState): void {
		foreach ($input->players as $player) {
			if ($player->userId === null || isset($rankState[$player->userId])) {
				continue;
			}
			$rankState[$player->userId] = $this->ratingRepository->getPlayerRankOnDate($player->userId, $input->startedAt);
		}
	}

	/**
	 * @param array<int, int> $rankState
	 * @return RankDeltaResult[]
	 */
	private function calculateDeltas(GameCalculationInput $input, array $rankState): array {
		$teams = [];
		$registeredPlayers = [];
		$minSkill = 99999;
		$maxSkill = 0;

		foreach ($input->players as $player) {
			$minSkill = min($minSkill, $player->skill);
			$maxSkill = max($maxSkill, $player->skill);

			$teamId = $player->teamId ?? 0;
			$rankingPlayer = $this->createRankingPlayer($player, $rankState[$player->userId] ?? null);
			$teams[$teamId] ??= [];
			$teams[$teamId][$player->playerId] = $rankingPlayer;

			if ($player->userId !== null) {
				$registeredPlayers[] = $player;
			}
		}

		$rankDeltas = [];
		foreach ($registeredPlayers as $player) {
			$teamId = $player->teamId ?? 0;
			$teammates = $teams[$teamId] ?? [];
			$enemies = [];

			if ($input->solo) {
				$teammates = [$teams[$teamId][$player->playerId]];
				foreach ($teams[$teamId] ?? [] as $id => $playerInfo) {
					if ($id === $player->playerId) {
						continue;
					}
					$enemies[] = $playerInfo;
				}
			}
			else {
				foreach ($teams as $id => $team) {
					if ($id === $teamId) {
						continue;
					}
					$enemies = array_merge($enemies, $team);
				}
			}

			$rankDeltas[] = $this->rankDeltaCalculator->calculateForPlayer(
				$player->skill,
				$minSkill,
				$maxSkill,
				$teammates,
				$enemies,
				$input->code,
				$player->userId,
				$player->name,
				$input->startedAt,
				$rankState[$player->userId] ?? 100,
			);
		}

		return $rankDeltas;
	}

	private function createRankingPlayer(PlayerCalculationInput $player, ?int $rank): RankingPlayer {
		$rankingPlayer = new RankingPlayer();
		$rankingPlayer->name = $player->name;
		$rankingPlayer->skill = $player->skill;
		$rankingPlayer->id_team = $player->teamId;
		$rankingPlayer->id_user = $player->userId;
		$rankingPlayer->rank = $rank ?? $player->skill;
		return $rankingPlayer;
	}

	/**
	 * @param RankDeltaResult[] $rankDeltas
	 */
	private function persistGameDeltas(GameCalculationInput $input, array $rankDeltas): void {
		$expectedResultsJsonByUserId = [];
		foreach ($rankDeltas as $rankDelta) {
			$expectedResultsJsonByUserId[$rankDelta->userId] = $this->serializer->serialize($rankDelta->debug, 'json');
		}

		$this->ratingRepository->replaceGameRatings($input->code, $rankDeltas, $expectedResultsJsonByUserId);
	}

	public function recalculateFrom(GameSelection $selection): RecalculationSummary {
		$gamesProcessed = 0;
		$ratingDeltasWritten = 0;
		$affectedUserIds = [];
		$rankState = null;

		foreach ($this->sqlInputProvider->iterateRankableGames($selection) as $input) {
			if ($rankState === null) {
				$rankState = [];
			}

			$this->ensureRankState($input, $rankState);
			$rankDeltas = $this->calculateDeltas($input, $rankState);
			$this->persistGameDeltas($input, $rankDeltas);

			foreach ($rankDeltas as $rankDelta) {
				$rankState[$rankDelta->userId] = max(0, (int)round(($rankState[$rankDelta->userId] ?? 100) + $rankDelta->difference));
				$affectedUserIds[$rankDelta->userId] = $rankDelta->userId;
			}

			$gamesProcessed++;
			$ratingDeltasWritten += count($rankDeltas);
		}

		$this->ratingRepository->recalculateUserRanks(array_values($affectedUserIds));

		return new RecalculationSummary(
			$gamesProcessed,
			$ratingDeltasWritten,
			array_values($affectedUserIds),
		);
	}

}
