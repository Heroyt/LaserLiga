<?php

namespace App\Services;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\Player;
use App\Models\Auth\User;
use DateTimeImmutable;
use DateTimeInterface;
use Dibi\DateTime;
use Dibi\Exception;
use Dibi\Row;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;
use Lsr\Core\Exceptions\ValidationException;
use Nette\Caching\Cache as CacheParent;
use Nette\Utils\Strings;
use Throwable;

class PlayerUserService
{

	/** @var int Difference of RATING_RATIO_CONSTANT between players should mean that one player is 10 times more likely to win */
	public const RATING_RATIO_CONSTANT = 300;
	/** @var int How strongly a result should affect the rating change */
	public const K_FACTOR = 30;

	public const MIN_PADDING = 50;
	public const MAX_PADDING = 0;

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

		$queries = PlayerFactory::getPlayersWithGamesUnionQueries(gameFields: ['id_arena']);
		$query = new Fluent(
			DB::getConnection()
				->select('%SQL [id_arena]', 'DISTINCT')
				->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]')
				->where('[id_user] = %i', $user->id)
		);
		$player->stats->arenasPlayed = $query
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

			/** @var Row $values Game's statistics */
			$values = DB::select("[{$system}_players]", 'AVG([skill]) as [avg], MAX([skill]) as [max], MIN([skill]) as [min]')
									->where('[id_game] = %i', $gameId)
									->cacheTags('games', 'games/'.$system, 'games/'.$code, 'averageSkill')
									->fetch();
			/** @var float $averageSkill */
			$averageSkill = $values->avg;
			/** @var int $maxSkill */
			$maxSkill = $values->max;
			/** @var int $minSkill */
			$minSkill = $values->min;

			$currentRank = $this->calculateRankForGamePlayer(
				$maxSkill,
				$minSkill,
				$skill,
				$averageSkill,
				$currentRank,
				$code,
				$user,
				$date
			);
		}

		return (int) round($currentRank);
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

		$skillSum = 0;
		$maxSkill = 0;
		$minSkill = 99999;
		foreach ($game->getPlayers() as $gamePlayer) {
			$skill = $gamePlayer->skill;
			$skillSum += $skill;
			if ($skill > $maxSkill) {
				$maxSkill = $skill;
			}
			if ($skill < $minSkill) {
				$minSkill = $skill;
			}
		}

		$averageSkill = $skillSum / $game->getPlayerCount();

		return $this->calculateRankForGamePlayer(
			$maxSkill,
			$minSkill,
			$player->getSkill(),
			$averageSkill,
			$currentRank,
			$game->code,
			$user,
			$game->start ?? new DateTimeImmutable()
		);
	}

	/**
	 * @param int                              $maxSkill
	 * @param int                              $minSkill
	 * @param int                              $skill
	 * @param float|int                        $averageSkill
	 * @param int|float                        $currentRank
	 * @param string                           $code
	 * @param User|\App\GameModels\Auth\Player $user
	 * @param DateTimeInterface                $date
	 *
	 * @return int
	 * @throws Exception
	 */
	protected function calculateRankForGamePlayer(int $maxSkill, int $minSkill, int $skill, float|int $averageSkill, int|float $currentRank, string $code, User|\App\GameModels\Auth\Player $user, DateTimeInterface $date) : int {
		$ratingDiff = 0.0;

		$minSkill -= $this::MIN_PADDING;
		$maxSkill += $this::MAX_PADDING;

		// Check to prevent division by 0
		if ($maxSkill > 0 && $maxSkill !== $minSkill) {
			// Normalize the real skill to values between 0 and 1
			$normalizedSkill = ($skill - $minSkill) / ($maxSkill - $minSkill);

			$diff = $averageSkill - $currentRank;
			// Magic ELO formula -> same principle as chess
			$expected = 1 / (1 + 10 ** ($diff / $this::RATING_RATIO_CONSTANT));
			$ratingDiff = $this::K_FACTOR * ($normalizedSkill - $expected);
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

	private function selectUserClassicGames(User $user, mixed ...$args) : Fluent {
		$queries = PlayerFactory::getPlayersWithGamesUnionQueries(gameFields: ['timing_game_length'], playerFields: ['shots', 'hits', 'deaths']);
		$query = new Fluent(
			DB::getConnection()
				->select(...$args)
				->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]')
				->where('[id_user] = %i', $user->id)
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

}