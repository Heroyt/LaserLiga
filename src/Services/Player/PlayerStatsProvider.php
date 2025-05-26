<?php

namespace App\Services\Player;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\Auth\Player;
use App\Models\Auth\User;
use App\Models\DataObjects\Player\AggregatedPlayerStats;
use App\Models\DataObjects\Player\PlayerGameSkillRow;
use App\Models\DataObjects\Player\PlayerStats;
use DateTimeInterface;
use Dibi\Exception;
use Lsr\Db\DB;
use Lsr\Db\Dibi\Fluent;
use Lsr\Orm\Exceptions\ValidationException;
use Throwable;

readonly class PlayerStatsProvider
{

	public function __construct(
		private RankCalculator $rankCalculator,
	) {
	}

	/**
	 * Calculate all stats for a player
	 *
	 * @param Player|User            $player
	 * @param DateTimeInterface|null $until
	 *
	 * @return PlayerStats
	 * @throws ValidationException
	 * @throws Exception
	 * @throws Throwable
	 */
	public function calculatePlayerStats(Player|User $player, ?DateTimeInterface $until = null): PlayerStats {
		$query = $this->selectUserClassicGames(
			$player,
			'AVG([accuracy]) as [accuracy], SUM([hits]) as [hits], SUM([deaths]) as [deaths], AVG([position]) as [position], AVG([shots]) as [averageShots], MAX([accuracy]) as [maxAccuracy], SUM([shots]) as [shots], SUM([timing_game_length]) as [minutes], MAX([score]) as [maxScore], MAX([skill]) as [maxSkill]'
		);
		if (isset($until)) {
			$query->where('start <= %dt', $until);
		}
		$aggregateValues = $query->fetchDto(AggregatedPlayerStats::class);
		return new PlayerStats(
			gamesPlayed          : $this->calculatePlayerGamesPlayed($player, $until),
			arenasPlayed         : $this->calculatePlayerArenasPlayed($player, $until),
			rank                 : $this->calculatePlayerRating($player),
			averageAccuracy      : (float) $aggregateValues->accuracy,
			averagePosition      : (float) $aggregateValues->position,
			maxAccuracy          : (int)$aggregateValues->maxAccuracy,
			maxScore             : (int)$aggregateValues->maxScore,
			maxSkill             : (int)$aggregateValues->maxSkill,
			shots                : (int)$aggregateValues->shots,
			averageShots         : (float) $aggregateValues->averageShots,
			averageShotsPerMinute: (float) ($aggregateValues->shots / max(1, $aggregateValues->minutes ?? 1)),
			totalMinutes         : (int)($aggregateValues->minutes ?? 0),
			kd                   : (float) ($aggregateValues->deaths > 0.0 ? $aggregateValues->hits / $aggregateValues->deaths : 0.0),
			hits                 : (int)$aggregateValues->hits,
			deaths               : (int)$aggregateValues->deaths,
		);
	}

	/**
	 * Get a query for classic games that the given user played
	 *
	 * @param User|Player $user
	 * @param mixed       ...$args
	 *
	 * @return Fluent
	 */
	public function selectUserClassicGames(User|Player $user, mixed ...$args): Fluent {
		$queries = PlayerFactory::getPlayersWithGamesUnionQueries(
			gameFields  : ['timing_game_length'],
			playerFields: ['shots', 'hits', 'deaths']
		);
		$query = DB::getConnection()->getFluent(
			DB::getConnection()
				->connection
				->select(...$args)
				->from('%sql', '((' . implode(') UNION ALL (', $queries) . ')) [t]')
				->where('[id_user] = %i AND [shots] > 30', $user->id)
		);
		$query->cacheTags('user/games', 'user/' . $user->id . '/games')
			// Rankable games are differentiated by its game mode
			  ->where('[id_mode] IN %sql', DB::select(AbstractMode::TABLE, 'id_mode')->where('[rankable] = 1'));
		return $query;
	}

	/**
	 * Calculate how many games had the player played
	 *
	 * @param Player|User            $player
	 * @param DateTimeInterface|null $until
	 *
	 * @return int
	 */
	public function calculatePlayerGamesPlayed(Player|User $player, ?DateTimeInterface $until = null): int {
		$queries = PlayerFactory::getPlayersWithGamesUnionQueries();
		$query = DB::getConnection()->getFluent(
			DB::getConnection()
				->connection
				->select('COUNT(*)')
				->from('%sql', '((' . implode(') UNION ALL (', $queries) . ')) [t]')
				->where('[id_user] = %i', $player->id)
		);
		if (isset($until)) {
			$query->where('start <= %dt', $until);
		}
		return (int)$query
			->cacheTags(
				'user/stats',
				'user/stats/gameCount',
				'user/' . $player->id . '/stats',
				'user/' . $player->id . '/stats/gameCount'
			)
			->fetchSingle();
	}

	/**
	 * Calculate how many arenas had the player played in
	 *
	 * @param Player|User            $player
	 * @param DateTimeInterface|null $until
	 *
	 * @return int
	 * @throws ValidationException
	 */
	public function calculatePlayerArenasPlayed(Player|User $player, ?DateTimeInterface $until = null): int {
		if ($player instanceof User) {
			$player = $player->createOrGetPlayer();
		}

		$query = $player->queryPlayedArenas();
		if (isset($until)) {
			$query->where('start <= %dt', $until);
		}
		return $query
			->cacheTags(
				'user/stats',
				'user/stats/arenaCount',
				'user/' . $player->id . '/stats',
				'user/' . $player->id . '/stats/arenaCount'
			)
			->count();
	}

	/**
	 * Calculate a player's rating. Checks for all unprocessed games.
	 *
	 * @param User|Player            $player
	 * @param DateTimeInterface|null $until
	 *
	 * @return int
	 * @throws ValidationException
	 * @throws Exception
	 * @throws Throwable
	 */
	public function calculatePlayerRating(User|Player $player, ?DateTimeInterface $until = null): int {
		// Get game codes of not yet processed games that are rankable
		$query = $this->selectUserClassicGames($player, '[code], [system], [id_game], [skill], [start]');
		if (isset($until)) {
			$query->where('start <= %dt', $until);
		}
		$gameCodes = $query
			// Exclude already processed games
			->where(
				'[code] NOT IN %sql',
				DB::select('player_game_rating', 'code')
				  ->where('[id_user] = %i', $player->id)
			)
			->orderBy('start')
			->fetchAllDto(PlayerGameSkillRow::class);

		$currentRank = ($player instanceof User ? $player->createOrGetPlayer()->stats->rank : $player->stats->rank);

		// Calculate rating difference for each game
		foreach ($gameCodes as $row) {
			$currentRank = $this->rankCalculator->calculateRankForGameCode(
				$player,
				$row->code,
				$row->system,
				$row->id_game,
				$row->start,
				$row->skill
			);
		}

		return (int)$currentRank;
	}

	/**
	 * Get player's rank
	 *
	 * @param User|Player            $player
	 * @param DateTimeInterface|null $until
	 *
	 * @return int
	 * @throws ValidationException
	 */
	public function getPlayerRating(User|Player $player, ?DateTimeInterface $until = null): int {
		if (isset($until)) {
			return $this->rankCalculator->getPlayerRankOnDate($player->id, $until);
		}
		return ($player instanceof User ? $player->createOrGetPlayer()->stats->rank : $player->stats->rank);
	}

}