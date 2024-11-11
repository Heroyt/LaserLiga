<?php

namespace App\Services\Player;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player as GamePlayer;
use App\GameModels\Game\Team;
use App\Models\Auth\Player;
use App\Models\Auth\User;
use App\Models\DataObjects\Ranking\PlayerGameRating;
use App\Models\DataObjects\Ranking\PlayerRankDiffResult;
use App\Models\DataObjects\Ranking\PlayerType;
use App\Models\DataObjects\Ranking\RankingPlayer;
use DateTimeImmutable;
use DateTimeInterface;
use Dibi\Exception;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
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
		private readonly Cache      $cache,
		private readonly Serializer $serializer,
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
					$player->rank = $group->getPlayerByName($player->name)?->getSkill();
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
						$player->rank = $group->getPlayerByName($player->name)?->getSkill();
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
	 * @param User|Player       $user
	 * @param DateTimeInterface $date
	 *
	 * @return int Current player's rank after the difference
	 * @throws Exception
	 * @throws ValidationException
	 * @post The difference is logged in the DB.
	 *
	 * @link https://en.wikipedia.org/wiki/Elo_rating_system
	 * @link https://ryanmadden.net/adapting-elo/
	 */
	public function calculateRankForGamePlayer(int $skill, int|float $minSkill, int|float $maxSkill, array $teammates, array $enemies, string $code, User|Player $user, DateTimeInterface $date): int {
		$ratingDiff = 0.0;
		$count = 0;

		// Add padding min and max skill by some amount
		$minSkill -= $this::MIN_PLAYER_PADDING;
		$maxSkill += $this::MAX_PLAYER_PADDING;

		$currentDateRank = $this->getPlayerRankOnDate($user->id, $date);

		$this->convertPlayersSkillToRank($teammates, $date);
		$this->convertPlayersSkillToRank($enemies, $date);

		$teamRank = $this->getTeamRank($teammates);
		$enemiesRank = $this->getTeamRank($enemies);

		$teamSkill = $this->getTeamSkill($teammates);
		$enemiesSkill = $this->getTeamSkill($enemies);

		$Q = 2.2 / ((($teamSkill > $enemiesSkill ? $teamRank - $enemiesRank : $enemiesRank - $teamRank) * 0.001) + 2.2);

		if ($user instanceof User) {
			$userName = $user->name;
		}
		else {
			$userName = $user->nickname;
		}

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

		// Check to prevent division by 0
		if ($maxSkill > 0 && $maxSkill !== $minSkill) {
			// Normalize the real skill to values between 0 and 1
			$normalizedSkill = ($skill - $minSkill) / ($maxSkill - $minSkill);
			foreach ($enemies as $enemy) {
				$result = new PlayerRankDiffResult(PlayerType::ENEMY, $enemy, $normalizedSkill);
				$this->calculateRankingResult($result, $skill, $currentDateRank, $minSkill, $maxSkill, $Q);
				$ratingDiff += $result->ratingDiff;
				$count++;
				$expectedResults['players'][] = $result;
			}
			foreach ($teammates as $teammate) {
				if ($teammate->id_user === $user->id) {
					continue;
				}
				$result = new PlayerRankDiffResult(PlayerType::TEAMMATE, $teammate, $normalizedSkill);
				$this->calculateRankingResult($result, $skill, $currentDateRank, $minSkill, $maxSkill, $Q);
				$ratingDiff += $result->ratingDiff;
				$count++;
				$expectedResults['players'][] = $result;
			}
		}

		if ($count > 0) { // Prevent division by 0
			// Multiply by the K_FACTOR but divide by the enemy count to maintain the difference range
			$ratingDiff *= $this::K_FACTOR / $count;
		}

		// Save difference
		$test = DB::select('player_game_rating', 'COUNT(*)')
		          ->where('[code] = %s AND [id_user] = %i', $code, $user->id)
		          ->fetchSingle(false);

		$expectedResultsJson = $this->serializer->serialize($expectedResults, 'json');

		// Limit rating diff to < -50, 50 >.
		$ratingDiff = max(min($ratingDiff, 50.0), -50.0);

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
			'normalized_skill' => ($normalizedSkill ?? null),
			'max_skill'        => $maxSkill,
			'min_skill'        => $minSkill,
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
	 * Calculates the average team rank.
	 *
	 * @param RankingPlayer[] $players
	 *
	 * @return float
	 */
	private function getTeamRank(array $players): float {
		$count = count($players);
		// Prevent division by 0
		if ($count === 0) {
			return 0.0;
		}
		return array_reduce($players, static fn($a, $b) => $a + ($b->rank ?? $b->skill), 0) / $count;
	}

	/**
	 * Calculates the average skill of given players
	 *
	 * @param RankingPlayer[] $players
	 *
	 * @return float
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


		// Magic ELO formula -> same principle as chess
		$result->expectedResult = 1 / (1 + 10 ** ($diff / $this::RATING_RATIO_CONSTANT));
		$result->marginOfVictory = log(abs($skill - $result->player->skill) + 1) * $Q;

		$result->result = 1 / (1 + 100 ** ($normalizedEnemySkill - $result->normalizedSkill));
		$result->ratingDiff = ($result->result - $result->expectedResult) * $result->marginOfVictory;
		if ($result->type === PlayerType::TEAMMATE) {
			$result->ratingDiff *= $this::TEAMMATE_WEIGHT;
		}
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

		$players = $game->getPlayers()->getAll();
		$users = [];
		$teams = [];
		$maxSkill = 0;
		$minSkill = 99999;
		foreach ($players as $player) {
			/** @var Team $team */
			$team = $player->getTeam();
			$skill = $player->getSkill();
			if ($skill > $maxSkill) {
				$maxSkill = $skill;
			}
			if ($skill < $minSkill) {
				$minSkill = $skill;
			}

			if (!isset($teams[$team->id])) {
				$teams[$team->id] = [];
			}
			$teams[$team->id][$player->id] = RankingPlayer::fromGamePlayer($player);

			try {
				if (!isset($teams[$team->id][$player->id]->id_user) && $game->getGroup() !== null) {
					$teams[$team->id][$player->id]->rank = $game->getGroup()
					                                            ->getPlayerByName($player->name)
					                                            ?->getSkill();
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
			/** @noinspection NullPointerExceptionInspection */
			if ($game->getMode()->isSolo()) {
				$teammates = [$teams[$player->team->id][$player->id]];
				foreach ($teams[$player->team->id] as $id => $playerInfo) {
					if ($id === $player->id) {
						continue;
					}
					$enemies[] = $playerInfo;
				}
			}
			else {
				$enemyTeams = [];
				$teammates = $teams[$player->team->id];
				foreach ($teams as $id => $team) {
					if ($id === $player->team->id) {
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
			$this->recalculateUsersRanksFromDifference();
		}
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
		$user = $player->user;
		if (!isset($user)) {
			return -1;
		}

		if (!$player->getGame()->getMode()?->rankable) {
			return $user->stats->rank;
		}

		$game = $player->getGame();

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
		foreach ($game->getPlayers()->getAll() as $gamePlayer) {
			$skill = $gamePlayer->skill;
			if ($skill > $maxSkill) {
				$maxSkill = $skill;
			}
			if ($skill < $minSkill) {
				$minSkill = $skill;
			}

			$playerData = RankingPlayer::fromGamePlayer($gamePlayer);

			if (!isset($playerData->id_user) && $game->getGroup() !== null) {
				$playerData->rank = $game->getGroup()->getPlayerByName($playerData->name)?->getSkill();
			}
			if ($gamePlayer->team->id !== $player->team->id || $game->getMode()?->isSolo()) {
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