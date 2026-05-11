<?php

namespace App\Services\Player;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player as GamePlayer;
use App\GameModels\Game\Team;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\Player;
use App\Models\Auth\User;
use App\Models\DataObjects\Ranking\PlayerGameRating;
use App\Models\DataObjects\Ranking\RankingPlayer;
use App\Models\GameGroup;
use App\Services\Player\Ranking\RankDeltaCalculator;
use DateTimeImmutable;
use DateTimeInterface;
use Dibi\Exception;
use Lsr\Caching\Cache;
use Lsr\Db\DB;
use Lsr\LaserLiga\PlayerInterface;
use Lsr\Orm\Exceptions\ValidationException;
use Symfony\Component\Serializer\Serializer;
use Throwable;

/**
 * Class RankCalculator
 *
 * The RankCalculator class is responsible for calculating the rank of a player in a game based on their skill and game statistics.
 *
 * @package
 */
class RankCalculator
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

	public function __construct(
		private readonly Cache               $cache,
		private readonly Serializer          $serializer,
		private readonly RankDeltaCalculator $rankDeltaCalculator,
	) {
	}

	/**
	 * Calculates the rank for a given player in this game.
	 *
	 * This is a helper function that prepares all other values necessary for the ELO calculation.
	 *
	 * @param User|Player       $user
	 * @param string            $code   Game's code
	 * @param string            $system Game's system
	 * @param int               $gameId Game's ID
	 * @param DateTimeInterface $date   Game's start datetime
	 * @param int               $skill  Player's calculated skill
	 *
	 * @return float New rank
	 * @throws Exception
	 * @throws Throwable
	 * @throws ValidationException
	 */
	public function calculateRankForGameCode(User|Player $user, string $code, string $system, int $gameId, DateTimeInterface $date, int $skill): float {
		if ($user instanceof User) {
			$user = $user->createOrGetPlayer();
		}

		/** @var RankingPlayer[][] $values Game's player statistics */
		$values = DB::select(
			["[{$system}_players]", 'a'],
			'[a].[name], [a].[id_user], [a].[skill], [a].[id_team], %sql as [rank]',
			DB::select(['[player_game_rating]', 'b'], '100 + SUM([b].[difference])')->where(
				'[b].[id_user] = [a].[id_user] AND [b].[date] < %dt',
				$date
			)->fluent
		)->where('[a].[id_game] = %i', $gameId)->cacheTags(
			'games',
			'games/' . $system,
			'games/' . $code,
			'averageSkill'
		)->fetchAssocDto(RankingPlayer::class, 'id_team|[]');

		// Check if game is in a group - then for unregistered players, take their average skill as a rank
		$game = GameFactory::getByCode($code);
		$group = $game?->getGroup();

		/** @var RankingPlayer[] $teammates */
		$teammates = [];
		/** @var RankingPlayer[] $enemies */
		$enemies = [];

		$minSkill = 9999;
		$maxSkill = 0;

		$enemyTeams = [];

		if (count($values) === 1) {
			$values = array_shift($values);
			foreach ($values as $player) {
				if ($player->skill > $maxSkill) {
					$maxSkill = $player->skill;
				}
				if ($player->skill < $minSkill) {
					$minSkill = $player->skill;
				}

				if (!isset($player->id_user) && isset($group)) {
					$player->rank = $this->getPlayerGroupRank($player, $group);
				}

				if ($player->id_user === $user->id) {
					$teammates[] = $player;
					continue;
				}
				$enemies[] = $player;
			}
		}
		else {
			$foundPlayer = false;
			foreach ($values as $team) {
				foreach ($team as $key => $player) {
					if ($player->skill > $maxSkill) {
						$maxSkill = $player->skill;
					}
					if ($player->skill < $minSkill) {
						$minSkill = $player->skill;
					}

					if (!isset($player->id_user) && isset($group)) {
						$player->rank = $this->getPlayerGroupRank($player, $group);
					}

					if (!$foundPlayer && $player->id_user === $user->id) {
						$foundPlayer = true;
						unset($team[$key]);
						continue;
					}
					$team[$key] = $player;
				}
				if (!$foundPlayer) {
					$enemyTeams[] = $team;
					continue;
				}
				$teammates = array_values($team);
			}
			// Flatten the array
			$enemies = array_merge(...$enemyTeams);
		}

		return $this->calculateRankForGamePlayer(
			$skill,
			$minSkill,
			$maxSkill,
			$teammates,
			$enemies,
			$code,
			$user,
			$date
		);
	}

	/**
	 * Calculates the ELO change for each player based on the ELO ranking formula modified to suit the multiplayer aspect of the game.
	 *
	 * The algorithm bases its calculation on the player's skill rating calculated in the Player classes.
	 * The skill rating should provide a more balanced look on the real player skill rather than the player's score itself
	 * because it incorporates other statistics such as K:D ratio, accuracy, etc. and is not influenced by the game's length
	 * and the number of players.
	 *
	 * @param int               $skill
	 * @param int|float         $minSkill
	 * @param int|float         $maxSkill
	 * @param RankingPlayer[]   $teammates
	 * @param RankingPlayer[]   $enemies
	 * @param string            $code
	 * @param User|PlayerInterface       $user
	 * @param DateTimeInterface $date
	 *
	 * @return int Current player's rank after the difference
	 * @throws ValidationException
	 * @post The difference is logged in the DB.
	 *
	 * @link https://en.wikipedia.org/wiki/Elo_rating_system
	 * @link https://ryanmadden.net/adapting-elo/
	 */
	public function calculateRankForGamePlayer(int $skill, int|float $minSkill, int|float $maxSkill, array $teammates, array $enemies, string $code, User|PlayerInterface $user, DateTimeInterface $date): int {
		assert($user instanceof Player || $user instanceof User);

		$currentDateRank = $this->getPlayerRankOnDate($user->id, $date);

		$this->convertPlayersSkillToRank($teammates, $date);
		$this->convertPlayersSkillToRank($enemies, $date);

		if ($user instanceof User) {
			$userName = $user->name;
		}
		else {
			$userName = $user->nickname;
		}

		$rankDelta = $this->rankDeltaCalculator->calculateForPlayer(
			$skill,
			$minSkill,
			$maxSkill,
			$teammates,
			$enemies,
			$code,
			$user->id,
			$userName,
			$date,
			$currentDateRank,
		);

		// Save difference
		$test = DB::select('player_game_rating', 'COUNT(*)')
		          ->where('[code] = %s AND [id_user] = %i', $code, $user->id)
		          ->fetchSingle(false);

		$expectedResultsJson = $this->serializer->serialize($rankDelta->debug, 'json');
		$ratingDiff = $rankDelta->difference;

		if ($user instanceof User) {
			$user = $user->createOrGetPlayer();
		}

		// Check if the user would have a negative rank.
		// If so, prevent it and decrease the difference, so they would end up with 0 rank.
		$rank = $user->stats->rank;
		$user->stats->rank = (int)round($user->stats->rank + $ratingDiff);
		// -100 because the rank starts at 100 by default.
		if ($user->stats->rank < -100) {
			$ratingDiff = (float)(-100 - $rank);
			$user->stats->rank = -100;
		}

		$insertData = [
			'code'             => $code,
			'id_user'          => $user->id,
			'difference'       => $ratingDiff,
			'date'             => $date,
			'expected_results' => $expectedResultsJson,
			'normalized_skill' => $rankDelta->normalizedSkill,
			'max_skill'        => $rankDelta->maxSkill,
			'min_skill'        => $rankDelta->minSkill,
		];
		if ($test > 0) {
			DB::update('player_game_rating', $insertData, ['[code] = %s AND [id_user] = %i', $code, $user->id]);
		}
		else {
			DB::insertIgnore('player_game_rating', $insertData);
		}

		return $user->stats->rank;
	}

	/**
	 * Gets player's rank on specified date
	 *
	 * @param int               $userId
	 * @param DateTimeInterface $date
	 *
	 * @return int
	 */
	public function getPlayerRankOnDate(int $userId, DateTimeInterface $date): int {
		return max(
			0,
			(int)round(
				DB::select('player_game_rating', '100 + SUM([difference])')->where(
					'[id_user] = %i AND [date] < %dt',
					$userId,
					$date
				)->fetchSingle(false) ?? 100
			)
		);
	}

	/**
	 * Sets the rank for each player.
	 *
	 * Registered players get the rank by date and for other, their rank is their skill in game.
	 *
	 * @param RankingPlayer[]   $players
	 * @param DateTimeInterface $date
	 *
	 * @return void
	 */
	private function convertPlayersSkillToRank(array $players, DateTimeInterface $date): void {
		foreach ($players as $player) {
			if (isset($player->rank)) {
				continue;
			}
			if (!isset($player->id_user)) {
				$player->rank = $player->skill;
				continue;
			}
			$player->rank = $this->getPlayerRankOnDate($player->id_user, $date);
		}
	}

	/**
	 * Calculates a weighted average of the unregistered player's rank in a group
	 */
	public function getPlayerGroupRank(GamePlayer|RankingPlayer $player, GameGroup $group): ?int {
		try {
			$groupPlayer = $group->getPlayerByName($player->name);
		} catch (Throwable) {
			return null;
		}
		if ($groupPlayer === null) {
			return null;
		}
		return (int)(($player->skill * 0.7) + (0.3 * $groupPlayer->getSkill()));
	}

	/**
	 * Re-calculates all player ratings for given game.
	 *
	 * @template T of Team
	 * @template P of GamePlayer
	 *
	 * @param Game<T,P> $game
	 *
	 * @return void
	 * @throws Exception
	 * @throws Throwable
	 * @throws ValidationException
	 * @throws GameModeNotFoundException
	 */
	public function recalculateRatingForGame(Game $game): void {
		if (!$game->getMode()?->rankable) {
			return;
		}

		/** @var DateTimeInterface $date */
		$date = $game->start;

		$players = $game->players->getAll();
		$users = [];
		$teams = [];
		$maxSkill = 0;
		$minSkill = 99999;
		foreach ($players as $player) {
			$teamId = $player->team?->id ?? 0;
			$skill = $player->skill;
			if ($skill > $maxSkill) {
				$maxSkill = $skill;
			}
			if ($skill < $minSkill) {
				$minSkill = $skill;
			}

			if (!isset($teams[$teamId])) {
				$teams[$teamId] = [];
			}
			$rankingPlayer = RankingPlayer::fromGamePlayer($player);
			$teams[$teamId][$player->id] = $rankingPlayer;

			try {
				if (!isset($rankingPlayer->id_user) && $game->getGroup() !== null) {
					$rankingPlayer->rank = $this->getPlayerGroupRank($rankingPlayer, $game->getGroup());
				}
			} catch (Throwable) {
			}

			if (isset($player->user)) {
				$users[] = $player;
			}
		}

		// Convert all player's skills to rank
		foreach ($teams as $team) {
			$this->convertPlayersSkillToRank($team, $date);
		}

		foreach ($users as $player) {
			$enemies = [];
			$teamId = $player->team?->id ?? 0;
			/** @noinspection NullPointerExceptionInspection */
			if ($game->getMode()->isSolo()) {
				$teammates = [$teams[$teamId][$player->id]];
				foreach ($teams[$teamId] as $id => $playerInfo) {
					if ($id === $player->id) {
						continue;
					}
					$enemies[] = $playerInfo;
				}
			}
			else {
				$enemyTeams = [];
				$teammates = $teams[$teamId];
				foreach ($teams as $id => $team) {
					if ($id === $teamId) {
						continue;
					}
					$enemyTeams[] = $team;
				}
				$enemies = array_merge(...$enemyTeams);
			}

			$this->calculateRankForGamePlayer(
				$player->skill,
				$minSkill,
				$maxSkill,
				$teammates,
				$enemies,
				$game->code,
				$player->user,
				$date
			);
		}
		$this->recalculateUsersRanksFromDifference();
	}

	/**
	 * Recalculates player's rank by summing all game values.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function recalculateUsersRanksFromDifference(): void {
		DB::getConnection()->query(
			"UPDATE %n [a] SET [rank] = 100 + COALESCE((SELECT SUM([b].[difference]) FROM [player_game_rating] [b] WHERE [a].[id_user] = [b].[id_user]),0)",
			Player::TABLE
		);
		DB::getConnection()->query(
			"UPDATE %n [a] SET [rank] = 0 WHERE [rank] < 0",
			Player::TABLE
		);
		$this->cache->clean([$this->cache::Tags => [Player::TABLE, Player::TABLE . '/query']]);
	}

	/**
	 * Re-calculates player's rating.
	 *
	 * @template T of Team
	 * @template G of Game
	 * @param GamePlayer<G,T> $player
	 *
	 * @return int
	 * @throws Exception
	 * @throws Throwable
	 */
	public function recalculatePlayerGameRating(GamePlayer $player): int {
		/** @var LigaPlayer|null $user */
		$user = $player->user;
		if (!isset($user)) {
			return -1;
		}

		if (!$player->game->mode?->rankable) {
			return $user->stats->rank;
		}

		$game = $player->game;

		$rating = DB::select('player_game_rating', '*')
		            ->where('[code] = %s AND [id_user] = %i', $game->code, $user->id)
		            ->fetchDto(PlayerGameRating::class, cache: false);
		if (isset($rating)) {
			// Reset already calculated rating
			$user->stats->rank = (int)round($user->stats->rank - $rating->difference);
			DB::delete('player_game_rating', ['[code] = %s AND [id_user] = %i', $game->code, $user->id]);
		}


		/** @var RankingPlayer[] $teammates */
		$teammates = [];
		/** @var RankingPlayer[] $enemies */
		$enemies = [];

		$maxSkill = 0;
		$minSkill = 99999;
		foreach ($game->players->getAll() as $gamePlayer) {
			$skill = $gamePlayer->skill;
			if ($skill > $maxSkill) {
				$maxSkill = $skill;
			}
			if ($skill < $minSkill) {
				$minSkill = $skill;
			}

			$playerData = RankingPlayer::fromGamePlayer($gamePlayer);

			if (!isset($playerData->id_user) && $game->group !== null) {
				$playerData->rank = $this->getPlayerGroupRank($playerData, $game->group);
			}
			if (($gamePlayer->team?->id ?? 0) !== ($player->team?->id ?? 0) || $game->mode?->isSolo()) {
				$enemies[] = $playerData;
			}
			else {
				$teammates[] = $playerData;
			}
		}


		return $this->calculateRankForGamePlayer(
			$player->getSkill(),
			$minSkill,
			$maxSkill,
			$teammates,
			$enemies,
			$game->code,
			$user,
			$game->start ?? new DateTimeImmutable
		);
	}

}
