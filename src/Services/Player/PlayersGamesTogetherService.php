<?php

namespace App\Services\Player;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Team;
use App\Models\Auth\Player;
use App\Models\DataObjects\Game\GamesTogetherRow;
use App\Models\DataObjects\GamesTogether;
use Lsr\Caching\Cache;
use Lsr\Db\DB;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Throwable;

readonly class PlayersGamesTogetherService
{

	public function __construct(private Cache $cache) {
	}

	/**
	 * @param Player    $player1
	 * @param Player    $player2
	 * @param numeric[] $modes
	 * @param bool      $cache
	 *
	 * @return GamesTogether
	 * @throws Throwable
	 */
	public function getGamesTogether(Player $player1, Player $player2, array $modes = [], bool $cache = true): GamesTogether {
		if (!$cache) {
			return $this->loadGamesTogether($player1, $player2);
		}
		$cacheKey = 'games_together_' . min($player1->id, $player2->id) . '-' . max($player1->id, $player2->id);
		return $this->cache->load(
			$cacheKey,
			fn() => $this->loadGamesTogether($player1, $player2, $modes),
			[
				Cache::Tags => [
					'players',
					'user/' . $player1->id . '/games',
					'user/' . $player2->id . '/games',
					'user/compare',
					'user/compare/' . $player1->id . '/' . $player2->id,
					'user/compare/' . $player2->id . '/' . $player1->id,
				],
			]
		);
	}

	/**
	 * @param Player    $player1
	 * @param Player    $player2
	 * @param numeric[] $modes
	 *
	 * @return GamesTogether
	 * @throws Throwable
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws DirectoryCreationException
	 */
	private function loadGamesTogether(Player $player1, Player $player2, array $modes = []): GamesTogether {
		$gamesQuery = DB::getConnection()
			->connection
		                ->select(
			                "[id_game], [type], [code], [id_mode], GROUP_CONCAT([vest] SEPARATOR ',') as [vests], GROUP_CONCAT([id_team] SEPARATOR ',') as [teams], GROUP_CONCAT([id_user] SEPARATOR ',') as [users], GROUP_CONCAT([name] SEPARATOR ',') as [names]"
		                )
		                ->from(
			                PlayerFactory::queryPlayersWithGames(playerFields: ['vest'], modeFields: ['type'])
			                             ->where('[id_user] IN %in', [$player1->id, $player2->id])
				                ->fluent,
			                'players'
		                )
		                ->groupBy('id_game')
		                ->having('COUNT([id_game]) = 2');

		// Filter by game modes
		if (!empty($modes)) {
			// Cast all ids to int
			foreach ($modes as $key => $mode) {
				$modes[$key] = (int)$mode;
			}
			$gamesQuery->where('[id_mode] IN %in', $modes);
		}

		$games = DB::getConnection()->getFluent($gamesQuery)
			->cacheTags(
				'players',
				'user/' . $player1->id . '/games',
				'user/' . $player2->id . '/games',
				'user/compare',
				'user/compare/' . $player1->id . '/' . $player2->id,
				'user/compare/' . $player2->id . '/' . $player1->id
			)
			->fetchAllDto(GamesTogetherRow::class);

		$data = new GamesTogether($player1, $player2);

		$data->gameCount = count($games);
		foreach ($games as $gameRow) {
			$game = GameFactory::getByCode($gameRow->code);
			assert($game instanceof Game);
			$data->gameCodes[] = $gameRow->code;
			$user1 = null;
			if (!empty($gameRow->users)) {
				$users = explode(',', $gameRow->users);
				$user1 = $users[0];
			}
			$vest1 = $vest2 = null;
			if (!empty($gameRow->vests)) {
				$vests = explode(',', $gameRow->vests);
				$vest1 = $vests[0];
				if (count($vests) > 1) {
					$vest2 = $vests[1];
				}
			}
			$team1 = $team2 = null;
			if (!empty($gameRow->teams)) {
				$teams = explode(',', $gameRow->teams);
				$team1 = $teams[0];
				if (count($teams) > 1) {
					$team2 = $teams[1];
				}
			}
			if (((int)$user1) === $player1->id) {
				$currentPlayer = $game->getVestPlayer($vest1);
				$otherPlayer = $game->getVestPlayer($vest2);
			}
			else {
				$currentPlayer = $game->getVestPlayer($vest2);
				$otherPlayer = $game->getVestPlayer($vest1);
			}

			if ($currentPlayer === null || $otherPlayer === null) {
				continue;
			}

			$teammates = $team1 === $team2 && $gameRow->type === GameModeType::TEAM;
			if ($teammates) {
				$data->gameCodesTogether[] = $gameRow->code;
				$data->gameCountTogether++;
				/** @var Team|null $winTeam */
				$winTeam = $game->mode?->getWin($game);
				if (isset($winTeam) && $winTeam->id === (int)$team1) {
					$data->winsTogether++;
				}
				else if ($winTeam === null) {
					$data->drawsTogether++;
				}
				else {
					$data->lossesTogether++;
				}

				$data->hitsTogether += $currentPlayer->getHitsPlayer($otherPlayer);
				$data->deathsTogether += $otherPlayer->getHitsPlayer($currentPlayer);
			}
			else {
				$data->gameCodesEnemy[] = $gameRow->code;
				$data->gameCountEnemy++;

				if ($game->getMode()?->isTeam()) {
					$data->gameCountEnemyTeam++;
					/** @var Team|null $winTeam */
					$winTeam = $game->mode->getWin($game);
					if ($currentPlayer->team?->id === $winTeam?->id) {
						$data->winsEnemy++;
					}
					elseif ($otherPlayer->team?->id === $winTeam?->id) {
						$data->lossesEnemy++;
					}
					else {
						$data->drawsEnemy++;
					}
				}
				else {
					$data->gameCountEnemySolo++;
					if ($currentPlayer->score === $otherPlayer->score) {
						$data->drawsEnemy++;
					}
					elseif ($currentPlayer->score > $otherPlayer->score) {
						$data->winsEnemy++;
					}
					else {
						$data->lossesEnemy++;
					}
				}

				$data->hitsEnemy += $currentPlayer->getHitsPlayer($otherPlayer);
				$data->deathsEnemy += $otherPlayer->getHitsPlayer($currentPlayer);
			}
		}

		return $data;
	}

}