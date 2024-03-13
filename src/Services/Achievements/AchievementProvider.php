<?php

namespace App\Services\Achievements;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Models\Achievements\Achievement;
use App\Models\Achievements\AchievementClaimDto;
use App\Models\Achievements\PlayerAchievement;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\Player;
use App\Services\PushService;
use Dibi\Exception;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Throwable;

class AchievementProvider
{

	/**
	 * @var array<int,int>
	 */
	private array $counts;

	public function __construct(
		private readonly Cache       $cache,
		private readonly PushService $pushService,
	) {
	}

	public function getForGamePlayer(\App\GameModels\Game\Player $player): array {
		if (!isset($player->user)) {
			return [];
		}
		$achievements = [];
		$rows = DB::select('player_achievements', '*')
		          ->where('code = %s AND id_user = %i', $player->getGame()->code, $player->user->id)
		          ->cacheTags(
			          'user/achievements',
			          'user/' . $player->id . '/achievements',
			          'game/' . $player->getGame()->code . '/achievements'
		          )
		          ->fetchAll();
		foreach ($rows as $row) {
			$achievements[] = new PlayerAchievement(
				Achievement::get($row->id_achievement),
				$player->user,
				$player->getGame(),
				$row->datetime
			);
		}
		return $achievements;
	}

	public function getForGame(Game $game): array {
		$achievements = [];
		$rows = DB::select('player_achievements', '*')
		          ->where('code = %s', $game->code)
		          ->cacheTags('user/achievements', 'game/' . $game->code . '/achievements')
		          ->fetchAll();
		foreach ($rows as $row) {
			$achievements[] = new PlayerAchievement(
				Achievement::get($row->id_achievement),
				LigaPlayer::get($row->id_user),
				$game,
				$row->datetime
			);
		}
		return $achievements;
	}

	/**
	 * @param Player $player
	 *
	 * @return AchievementClaimDto[]
	 * @throws ValidationException
	 */
	public function getAllClaimedUnclaimed(Player $player): array {
		$allAchievements = Achievement::query()->orderBy('type')->get();
		/** @var array<int,object{id_achievement:int,id_user:int,datetime:\DateTimeInterface,code:string}|null> $playerAchievements */
		$playerAchievements = $this->queryAchievementsForUser($player)->fetchAssoc('id_achievement');
		$counts = $this->getClaimedCounts();
		$achievements = [];
		foreach ($allAchievements as $achievement) {
			$achievements[] = new AchievementClaimDto(
				$achievement,
				isset($playerAchievements[$achievement->id]),
				($playerAchievements[$achievement->id] ?? null)?->code,
				($playerAchievements[$achievement->id] ?? null)?->datetime,
				$counts[$achievement->id] ?? 0,
			);
		}
		return $achievements;
	}

	public function queryAchievementsForUser(Player $player, string $select = '*'): Fluent {
		return DB::select('player_achievements', $select)
		         ->where('id_user = %i', $player->id)
		         ->orderBy('datetime')
		         ->cacheTags('user/achievements', 'user/' . $player->id . '/achievements');
	}

	/**
	 * @param Player $player
	 *
	 * @return PlayerAchievement[]
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws DirectoryCreationException
	 * @throws Throwable
	 */
	public function getForUser(Player $player): array {
		$achievements = [];

		$rows = $this->queryAchievementsForUser($player)->fetchAll();
		foreach ($rows as $row) {
			$achievements[] = new PlayerAchievement(
				Achievement::get((int)$row->id_achievement),
				$player,
				GameFactory::getByCode((string)$row->code),
				$row->datetime
			);
		}

		return $achievements;
	}

	/**
	 * @param Player $player
	 *
	 * @return Achievement[]
	 * @throws ValidationException
	 */
	public function getUnclaimedByUser(Player $player): array {
		return Achievement::query()
		                  ->where(
			                  'id_achievement NOT IN %sql',
			                  DB::select('player_achievements', 'id_achievement')
			                    ->where('id_user = %i', $player->id)
				                  ->fluent
		                  )
		                  ->cacheTags(
			                  'user/achievements',
			                  'user/achievements/unclaimed',
			                  'user/' . $player->id . '/achievements',
			                  'user/' . $player->id . '/achievements/unclaimed'
		                  )
		                  ->get();
	}

	/**
	 * @param PlayerAchievement[] $achievements
	 *
	 * @return void
	 * @throws Exception
	 */
	public function saveAchievements(array $achievements): void {
		if (count($achievements) === 0) {
			return;
		}
		$insertedAchievements = [];
		$cacheTags = ['user/achievements/count'];
		foreach ($achievements as $achievement) {
			$cacheTags[$achievement->player->id] = 'user/' . $achievement->player->id . '/achievements';
			$inserted = DB::insertIgnore(
				'player_achievements',
				[
					'id_user'        => $achievement->player->id,
					'id_achievement' => $achievement->achievement->id,
					'code'           => $achievement->game->code,
					'datetime'       => $achievement->datetime,
				]
			);
			if ($inserted > 0) {
				$insertedAchievements[$achievement->player->id] ??= [];
				$insertedAchievements[$achievement->player->id][] = $achievement;
			}
		}

		$this->cache->clean([$this->cache::Tags => array_values($cacheTags)]);

		// Send notifications
		foreach ($insertedAchievements as $userAchievements) {
			$this->pushService->sendAchievementNotification(...$userAchievements);
		}
	}

	/**
	 * @return int[]
	 */
	public function getClaimedCounts(): array {
		$this->counts ??= DB::select('player_achievements', 'id_achievement, COUNT(*) as [count]')
		                    ->groupBy('id_achievement')
		                    ->cacheTags('user/achievements', 'user/achievements/count')
		                    ->fetchPairs('id_achievement', 'count');

		return $this->counts;
	}

}