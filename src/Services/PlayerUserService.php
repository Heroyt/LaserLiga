<?php

namespace App\Services;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use DateTimeImmutable;
use DateTimeInterface;
use Dibi\DateTime;
use Dibi\Exception;
use Dibi\Row;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;
use Lsr\Core\Exceptions\ValidationException;
use Nette\Caching\Cache as CacheParent;
use Throwable;

class PlayerUserService
{

	/** @var int Difference of RATING_RATIO_CONSTANT between players should mean that one player is 10 times more likely to win */
	public const RATING_RATIO_CONSTANT = 400;
	/** @var int How strongly a result should affect the rating change */
	public const K_FACTOR = 10;

	public const        MIN_PADDING     = 50;
	public const        MAX_PADDING     = 0;
	public const        TEAMMATE_WEIGHT = 0.5;

	public function __construct(
		private readonly Cache $cache
	) {
	}

	/**
	 * Set a user to a player
	 *
	 * @param User   $user
	 * @param Player $player
	 *
	 * @return bool
	 * @throws ValidationException
	 */
	public function setPlayerUser(User $user, Player $player) : bool {
		if (isset($player->user) && $player->user->id === $user->id) {
			return true; // User is already set
		}
		$player->user = $user->player;
		if ($player->save()) {
			$player->game->clearCache();
			$this->cache->clean([
														CacheParent::Tags => [
															'user/'.$user->id.'/stats',
															'user/'.$user->id.'/games',
															'user/'.$user->id.'/lastGames',
														]
													]);
			$this->updatePlayerStats($user);
			return true;
		}
		return false;
	}

	public function updatePlayerStats(User $user) : void {
		$player = $user->createOrGetPlayer();
		$queries = PlayerFactory::getPlayersUnionQueries();
		$query = new Fluent(
			DB::getConnection()
				->select('COUNT(*)')
				->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]')
				->where('[id_user] = %i', $user->id)
		);
		$player->stats->gamesPlayed = (int) $query
			->cacheTags('user/stats', 'user/stats/gameCount', 'user/'.$user->id.'/stats', 'user/'.$user->id.'/stats/gameCount')
			->fetchSingle();

		$player->stats->arenasPlayed = $player->queryPlayedArenas()
																					->cacheTags('user/stats', 'user/stats/arenaCount', 'user/'.$user->id.'/stats', 'user/'.$user->id.'/stats/arenaCount')
																					->count();

		$player->stats->rank = $this->calculatePlayerRating($user);

		$aggregateValues = $this->selectUserClassicGames(
			$user,
			'AVG([accuracy]) as [accuracy],'.
			'SUM([hits]) as [hits],'.
			'SUM([deaths]) as [deaths],'.
			'AVG([position]) as [position],'.
			'AVG([shots]) as [averageShots],'.
			'MAX([accuracy]) as [maxAccuracy],'.
			'SUM([shots]) as [shots],'.
			'SUM([timing_game_length]) as [minutes],'.
			'MAX([score]) as [maxScore],'.
			'MAX([skill]) as [maxSkill]'
		)->fetch();
		$player->stats->averageAccuracy = $aggregateValues->accuracy ?? 0.0;
		$player->stats->averagePosition = $aggregateValues->position ?? 0.0;
		$player->stats->maxScore = $aggregateValues->maxScore ?? 0;
		$player->stats->maxAccuracy = $aggregateValues->maxAccuracy ?? 0;
		$player->stats->maxSkill = $aggregateValues->maxSkill ?? 0;
		$player->stats->totalMinutes = $aggregateValues->minutes ?? 0;
		$player->stats->shots = $aggregateValues->shots ?? 0;
		$player->stats->averageShots = $aggregateValues->averageShots ?? 0;
		$player->stats->averageShotsPerMinute = ($aggregateValues->shots ?? 0) / ($aggregateValues->minutes ?? 1);
		$player->stats->hits = $aggregateValues->hits ?? 0;
		$player->stats->deaths = $aggregateValues->deaths ?? 0;
		$player->stats->kd = ($aggregateValues->deaths ?? 0) !== 0 ? ($aggregateValues->hits ?? 0) / $aggregateValues->deaths : 0.0;

		$player->save();

		$this->updateUserTrophies($user);

		$this->recalculateUsersRanksFromDifference();
	}

	public function calculatePlayerRating(User $user) : int {
		// Get game codes of not yet processed games that are rankable
		$query = $this->selectUserClassicGames($user, '[code], [system], [id_game], [skill], [start]');
		$gameCodes = $query
			// Exclude already processed games
			->where(
				'[code] NOT IN %sql',
				DB::select('player_game_rating', 'code')
					->where('[id_user] = %i', $user->id)
			)
			->orderBy('start')
			->fetchAll();

		$currentRank = (float) $user->createOrGetPlayer()->stats->rank;

		// Calculate rating difference for each game
		foreach ($gameCodes as $row) {
			/** @var string $code */
			$code = $row->code;
			/** @var string $system */
			$system = $row->system;
			/** @var int $gameId */
			$gameId = $row->id_game;
			/** @var DateTime $date */
			$date = $row->start;
			/** @var int $skill */
			$skill = $row->skill;

			/** @var Row[][] $values Game's player statistics */
			$values = DB::select(
				["[{$system}_players]", 'a'],
				'[a].[id_user], [a].[skill], [a].[id_team], %sql as [rank]',
				DB::select(['[player_game_rating]', 'b'], '100 + SUM([b].[difference])')
					->where('[b].[id_user] = [a].[id_user] AND [b].[date] < %dt', $date)
					->fluent
			)
									->where('[a].[id_game] = %i', $gameId)
									->cacheTags('games', 'games/'.$system, 'games/'.$code, 'averageSkill')
									->fetchAssoc('id_team|[]');


			/** @var array{id_user:int|null,skill:int,id_team:int}[] $teammates */
			$teammates = [];
			/** @var array{id_user:int|null,skill:int,id_team:int}[] $enemies */
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
					if ($player->id_user === $user->id) {
						$teammates[] = $player->toArray();
						continue;
					}
					$enemies[] = $player->toArray();
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
						if (!$foundPlayer && $player->id_user === $user->id) {
							$foundPlayer = true;
							unset($team[$key]);
							continue;
						}
						$team[$key] = $player->toArray();
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

			$currentRank = $this->calculateRankForGamePlayer(
				$skill,
				$minSkill,
				$maxSkill,
				$currentRank,
				$teammates,
				$enemies,
				$code,
				$user,
				$date
			);
		}

		return (int) round($currentRank);
	}

	private function selectUserClassicGames(User $user, mixed ...$args) : Fluent {
		$queries = PlayerFactory::getPlayersWithGamesUnionQueries(gameFields: ['timing_game_length'], playerFields: ['shots', 'hits', 'deaths']);
		$query = new Fluent(
			DB::getConnection()
				->select(...$args)
				->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]')
				->where('[id_user] = %i AND [shots] > 30', $user->id)
		);
		$query
			->cacheTags('user/games', 'user/'.$user->id.'/games')
			// Rankable games are differentiated by its game mode
			->where(
				'[id_mode] IN %sql',
				DB::select(AbstractMode::TABLE, 'id_mode')->where('[rankable] = 1')
			);
		return $query;
	}

	/**
	 * Calculates the ELO change for each player based on the ELO ranking formula modified to suit the multiplayer aspect of the game.
	 *
	 * The algorithm bases its calculation on the player's skill rating calculated in the Player classes.
	 * The skill rating should provide a more balanced look on the real player skill rather than the player's score itself
	 * because it incorporates other statistics such as K:D ratio, accuracy, etc. and is not influenced by the game's length
	 * and the number of players.
	 *
	 * @param int                                                            $skill
	 * @param int|float                                                      $minSkill
	 * @param int|float                                                      $maxSkill
	 * @param int|float                                                      $currentRank
	 * @param array{id_user:int|null,skill:int,rank?:int|null,id_team:int}[] $teammates
	 * @param array{id_user:int|null,skill:int,rank?:int|null,id_team:int}[] $enemies
	 * @param string                                                         $code
	 * @param User|\App\Models\Auth\Player                                   $user
	 * @param DateTimeInterface                                              $date
	 *
	 * @return int Current player's rank after the difference
	 * @throws Exception If the SQL insert / update query fails
	 * @post The difference is logged in the DB.
	 *
	 * @link https://en.wikipedia.org/wiki/Elo_rating_system
	 * @link https://ryanmadden.net/adapting-elo/
	 */
	protected function calculateRankForGamePlayer(int $skill, int|float $minSkill, int|float $maxSkill, int|float $currentRank, array $teammates, array $enemies, string $code, User|\App\Models\Auth\Player $user, DateTimeInterface $date) : int {
		$ratingDiff = 0.0;
		$count = 0;

		// Add padding min and max skill by some amount
		$minSkill -= $this::MIN_PADDING;
		$maxSkill += $this::MAX_PADDING;

		$currentDateRank = $this->getPlayerRankOnDate($user->id, $date);

		$this->convertPlayersSkillToRank($teammates, $date);
		$this->convertPlayersSkillToRank($enemies, $date);

		$teamRank = $this->getTeamRank($teammates);
		$enemiesRank = $this->getTeamRank($enemies);

		$Q = 2.2 / ((abs($teamRank - $enemiesRank) * 0.001) + 2.2);

		// Check to prevent division by 0
		if ($maxSkill > 0 && $maxSkill !== $minSkill) {
			// Normalize the real skill to values between 0 and 1
			$normalizedSkill = ($skill - $minSkill) / ($maxSkill - $minSkill);
			foreach ($enemies as $enemy) {
				$diff = $enemy['rank'] - $currentDateRank;
				// Magic ELO formula -> same principle as chess
				$expectedResult = 1 / (1 + 10 ** ($diff / $this::RATING_RATIO_CONSTANT));
				$marginOfVictory = log(abs($diff) + 1) * $Q;
				$ratingDiff += ($normalizedSkill - $expectedResult) * $marginOfVictory;
				$count++;
			}
			foreach ($teammates as $teammate) {
				if ($teammate['id_user'] === $user->id) {
					continue;
				}
				$diff = $teammate['rank'] - $currentDateRank;
				// Magic ELO formula -> same principle as chess
				$expectedResult = 1 / (1 + 10 ** ($diff / $this::RATING_RATIO_CONSTANT));
				$marginOfVictory = log(abs($diff) + 1) * $Q;
				$ratingDiff += ($normalizedSkill - $expectedResult) * $marginOfVictory * $this::TEAMMATE_WEIGHT;
				$count++;
			}
		}

		if ($count > 0) { // Prevent division by 0
			// Multiply by the K_FACTOR but divide by the enemy count to maintain the difference range
			$ratingDiff *= $this::K_FACTOR / $count;
		}

		// Save difference
		$test = DB::select('player_game_rating', 'COUNT(*)')->where('[code] = %s AND [id_user] = %i', $code, $user->id)->fetchSingle(false);
		$insertData = ['code' => $code, 'id_user' => $user->id, 'difference' => $ratingDiff, 'date' => $date];
		if ($test > 0) {
			DB::update('player_game_rating', $insertData, ['[code] = %s AND [id_user] = %i', $code, $user->id]);
		}
		else {
			DB::insert('player_game_rating', $insertData);
		}

		// Update current rank
		$currentRank += $ratingDiff;

		return (int) round($currentRank);
	}

	/**
	 * Gets player's rank on specified date
	 *
	 * @param int               $userId
	 * @param DateTimeInterface $date
	 *
	 * @return int
	 */
	private function getPlayerRankOnDate(int $userId, DateTimeInterface $date) : int {
		return DB::select('player_game_rating', '100 + SUM([difference])')
						 ->where('[id_user] = %i AND [date] < %dt', $userId, $date)
						 ->fetchSingle(false) ?? 100;
	}

	/**
	 * @param array{id_user:int|null,skill:int,rank?:int|null,id_team:int}[] $players
	 * @param DateTimeInterface                                              $date
	 *
	 * @return void
	 */
	private function convertPlayersSkillToRank(array &$players, DateTimeInterface $date) : void {
		foreach ($players as &$player) {
			if (isset($player['rank'])) {
				continue;
			}
			if (!isset($player['id_user'])) {
				$player['rank'] = $player['skill'];
				continue;
			}
			$player['rank'] = $this->getPlayerRankOnDate($player['id_user'], $date);
		}
	}

	/**
	 * @param array{id_user:int|null,skill:int,rank:int,id_team:int}[] $players
	 *
	 * @return float
	 */
	private function getTeamRank(array $players) : float {
		$sum = 0;
		$count = count($players);
		// Prevent division by 0
		if ($count === 0) {
			return 0.0;
		}
		foreach ($players as $player) {
			$sum += $player['rank'];
		}
		return $sum / $count;
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function recalculateUsersRanksFromDifference() : void {
		DB::getConnection()
			->query("UPDATE %n [a] SET [RANK] = (SELECT 100 + SUM([b].[difference]) FROM [player_game_rating] [b] WHERE [a].[id_user] = [b].[id_user]) ", LigaPlayer::TABLE);
		App::getContainer()->getByType(Cache::class)?->clean([Cache::Tags => LigaPlayer::TABLE]);
	}

	/**
	 * @param Player $player
	 *
	 * @return int
	 * @throws Exception
	 * @throws Throwable
	 */
	public function recalculatePlayerGameRating(Player $player) : int {
		$user = $player->user;
		if (!isset($user)) {
			return -1;
		}

		if (!$player->game->mode->rankable) {
			return $user->stats->rank;
		}

		$game = $player->getGame();

		$rating = DB::select('player_game_rating', '*')
								->where('[code] = %s AND [id_user] = %i', $game->code, $user->id)
								->fetch(cache: false);
		if (isset($rating)) {
			// Reset already calculated rating
			$user->stats->rank -= $rating->difference;
			DB::delete('player_game_rating', ['[code] = %s AND [id_user] = %i', $game->code, $user->id]);
		}

		$currentRank = $user->stats->rank;

		/** @var array{id_user:int|null,skill:int,id_team:int}[] $teammates */
		$teammates = [];
		/** @var array{id_user:int|null,skill:int,id_team:int}[] $enemies */
		$enemies = [];

		$maxSkill = 0;
		$minSkill = 99999;
		foreach ($game->getPlayers() as $gamePlayer) {
			$skill = $gamePlayer->skill;
			if ($skill > $maxSkill) {
				$maxSkill = $skill;
			}
			if ($skill < $minSkill) {
				$minSkill = $skill;
			}

			$playerData = [
				'id_user' => $gamePlayer->user?->id,
				'skill'   => $gamePlayer->skill,
				'id_team' => $gamePlayer->team->id,
			];
			if ($game->mode->isSolo() || $gamePlayer->team->id !== $player->team->id) {
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
			$currentRank,
			$teammates,
			$enemies,
			$game->code,
			$user,
			$game->start ?? new DateTimeImmutable()
		);
	}

	public function recalculateRatingForGame(Game $game) : void {
		if (!$game->mode?->rankable) {
			return;
		}

		/** @var DateTimeInterface $date */
		$date = $game->start;

		$players = $game->getPlayers();
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
			$teams[$team->id][$player->id] = [
				'id_user' => $player->user?->id,
				'id_team' => $team->id,
				'skill'   => $player->skill,
			];

			if (isset($player->user)) {
				$users[] = $player;
			}
		}

		// Convert all player's skills to rank
		foreach ($teams as $id => $team) {
			$this->convertPlayersSkillToRank($teams[$id], $date);
		}

		foreach ($users as $player) {
			$enemies = [];
			if ($game->mode->isSolo()) {
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
				$player->user->stats->rank,
				$teammates,
				$enemies,
				$game->code,
				$player->user,
				$date
			);
			$this->recalculateUsersRanksFromDifference();
		}
	}

	public function updateUserTrophies(User $user) : void {
		$player = $user->createOrGetPlayer();

		$rows = $player->queryGames()
									 ->where(
										 '[code] NOT IN %sql',
										 DB::select('player_trophies_count', 'game')
											 ->where('[id_user] = %i', $user->id)
											 ->fluent
									 )
									 ->fetchAll(cache: false);
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

	public function updatePlayerTrophies(Player $player) : void {
		if (!isset($player->user) || !isset($player->user->id)) {
			return;
		}
		$values = [];
		foreach ($player->getAllBestAt() as $name => $trophy) {
			$values[] = [
				'id_user'  => $player->user->id,
				'name'     => $name,
				'game'     => $player->getGame()->code,
				'rankable' => $player->getGame()->getMode()->rankable,
			];
		}
		if (!empty($values)) {
			DB::replace('player_trophies_count', $values);
			App::getContainer()->getByType(Cache::class)->clean([Cache::Tags => ['user/'.$player->user->id.'/trophies']]);
		}
	}

}