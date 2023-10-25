<?php

namespace App\Services\Player;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Models\Auth\User;
use App\Models\PossibleMatch;
use Dibi\Exception;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Nette\Caching\Cache as CacheParent;
use Throwable;

/**
 * Class for handling registered user's connection to game players.
 */
readonly class PlayerUserService
{

	public function __construct(
		private Cache               $cache,
		private RankCalculator      $rankCalculator,
		private PlayerStatsProvider $playerStatsProvider,
	) {
	}

	/**
	 * Set a user to a player
	 *
	 * @template G of Game
	 * @template T of Team
	 *
	 * @param User        $user
	 * @param Player<G,T> $player
	 *
	 * @return bool
	 * @throws Exception
	 * @throws ValidationException
	 */
	public function setPlayerUser(User $user, Player $player): bool {
		if (isset($player->user) && $player->user->id === $user->id) {
			return true; // User is already set
		}
		$player->user = $user->player;
		if ($player->save()) {
			$player->game->clearCache();
			$this->cache->clean(
				[
					CacheParent::Tags => [
						'user/' . $user->id . '/stats',
						'user/' . $user->id . '/games',
						'user/' . $user->id . '/lastGames',
					],
				]
			);
			$this->updatePlayerStats($user);
			try {
				/** @var PossibleMatch|null $possibleMatch */
				$possibleMatch = PossibleMatch::query()->where(
					'[id_user] = %i AND [code] = %s',
					$user->id,
					$player->getGame()->code
				)->first();
				if (isset($possibleMatch)) {
					$possibleMatch->matched = true;
					$possibleMatch->save();
				}
			} catch (Throwable) {
			}
			return true;
		}
		return false;
	}

	/**
	 * Calculate and save all player's stats
	 *
	 * @param User $user
	 *
	 * @return void
	 * @throws Exception
	 * @throws ValidationException
	 *
	 * @see PlayerStatsProvider::calculatePlayerStats()
	 */
	public function updatePlayerStats(User $user): void {
		$player = $user->createOrGetPlayer();
		$player->stats = $this->playerStatsProvider->calculatePlayerStats($player);
		$player->save();

		$this->updateUserTrophies($user);

		$this->rankCalculator->recalculateUsersRanksFromDifference();
	}

	/**
	 * Check and save all trophy counts for a user
	 *
	 * @param User $user
	 *
	 * @return void
	 * @throws Throwable
	 * @throws ValidationException
	 */
	public function updateUserTrophies(User $user): void {
		$player = $user->createOrGetPlayer();

		$rows = $player->queryGames()->where(
			'[code] NOT IN %sql',
			DB::select('player_trophies_count', 'game')->where(
				'[id_user] = %i',
				$user->id
			)->fluent
		)->fetchAll(cache: false);
		foreach ($rows as $row) {
			$game = GameFactory::getById($row->id_game, ['system' => $row->system]);
			if (!isset($game)) {
				continue;
			}
			$userPlayer = $game->getPlayers()->get($row->vest);
			if (!isset($userPlayer)) {
				continue;
			}
			$this->updatePlayerTrophies($userPlayer);
		}
	}

	/**
	 * Check and save all trophy counts for a player in a game;
	 *
	 * @template G of Game
	 * @template T of Team
	 *
	 * @param Player<G,T> $player
	 *
	 * @return void
	 * @throws Exception
	 * @throws Throwable
	 * @throws ValidationException
	 * @throws GameModeNotFoundException
	 * @throws ModelNotFoundException
	 */
	public function updatePlayerTrophies(Player $player): void {
		if (!isset($player->user, $player->user->id)) {
			return;
		}
		$values = [];
		foreach ($player->getAllBestAt() as $name => $trophy) {
			$values[] = [
				'id_user'  => $player->user->id,
				'name'     => $name,
				'game'     => $player->getGame()->code,
				'rankable' => $player->getGame()->getMode()?->rankable ?? true,
				'datetime' => $player->getGame()->start,
			];
		}
		if (!empty($values)) {
			DB::replace('player_trophies_count', $values);
			$this->cache->clean([$this->cache::Tags => ['user/' . $player->user->id . '/trophies']]);
		}
	}

	/**
	 * Unset a user from a player
	 *
	 * @template G of Game
	 * @template T of Team
	 *
	 * @param Player<G,T> $player
	 *
	 * @return bool
	 * @throws Exception
	 * @throws ValidationException
	 */
	public function unsetPlayerUser(Player $player): bool {
		if (!isset($player->user)) {
			return true;
		}

		$user = $player->user;
		$player->user = null;
		if ($player->save()) {
			$player->game->clearCache();
			$this->cache->clean(
				[
					CacheParent::Tags => [
						'user/' . $user->id . '/stats',
						'user/' . $user->id . '/games',
						'user/' . $user->id . '/lastGames',
					],
				]
			);
			try {
				DB::delete('player_game_rating', ['id_user = %i AND code = %s', $user->id, $player->game->code]);
				DB::delete('possible_matches', ['id_user = %i AND code = %s', $user->id, $player->game->code]);
			} catch (Exception $e) {
				return false;
			}
			$this->updatePlayerStats($user->user);

			return true;
		}

		return false;
	}

	/**
	 * Find all possibly unconnected games for a user.
	 *
	 * The games are matched by the player's name and home arena.
	 *
	 * @param User $user
	 *
	 * @return PossibleMatch[]
	 * @throws ValidationException
	 */
	public function scanPossibleMatches(User $user): array {
		$possibleMatchesQuery = PlayerFactory::queryPlayersWithGames()->where('[id_user] IS NULL')->where(
			'[code] NOT IN %sql',
			DB::select(PossibleMatch::TABLE, 'code')->where('[id_user] = %i', $user->id)->fluent
		)->where('[name] LIKE %s', $user->name);
		if (isset($user->player->arena)) {
			$possibleMatchesQuery->where('[id_arena] = %i', $user->player->arena->id);
		}
		$possibleMatches = $possibleMatchesQuery->fetchAll(cache: false);

		foreach ($possibleMatches as $possibleMatch) {
			$match = new PossibleMatch();
			$match->user = $user;
			$match->code = $possibleMatch->code;
			$match->save();
		}

		$this->cache->clean([CacheParent::Tags => ['user/' . $user->id . '/possibleMatches',],]);

		return PossibleMatch::getForUser($user);
	}

}