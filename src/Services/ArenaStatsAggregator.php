<?php
declare(strict_types=1);

namespace App\Services;

use App\GameModels\Factory\GameFactory;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\DataObjects\Game\LeaderboardRecord;
use DateTimeInterface;
use Lsr\Db\DB;
use Lsr\Db\Dibi\Fluent;

class ArenaStatsAggregator
{

	/**
	 * @param Arena             $arena
	 * @param DateTimeInterface $date
	 *
	 * @return LeaderboardRecord[]
	 */
	public function getArenaDayPlayerLeaderboard(Arena $arena, DateTimeInterface $date): array {
		return $this->getArenaDayPlayerLeaderboardQuery($arena, $date)
		            ->orderBy('skill')
		            ->desc()
		            ->limit(20)
		            ->fetchAllDto(LeaderboardRecord::class);
	}

	public function getArenaDayPlayerLeaderboardQuery(Arena $arena, DateTimeInterface $date): Fluent {
		$queries = [];
		foreach (GameFactory::getSupportedSystems() as $key => $system) {
			/** @var int[] $gameIds */
			$gameIds = $arena->getGameIds($date, $system);
			$playerTable = $system . '_players';
			$gameTable = $system . '_games';
			$queries[] = DB::select(
				[$playerTable, 'p' . $key],
				'[p' . $key . '].[id_player],
			[p' . $key . '].[id_user],
			[g' . $key . '].[id_game],
			[g' . $key . '].[start] as [date],
			[g' . $key . '].[code] as [game_code],
			[p' . $key . '].[name],
			[p' . $key . '].[skill],
			((' . DB::select([$playerTable, 'pp1' . $key], 'COUNT(*) as [count]')->where(
					'[pp1' . $key . '].%n IN %in',
					'id_game',
					$gameIds
				)->where('[pp1' . $key . '].%n > [p' . $key . '].%n', 'skill', 'skill') . ')+1) as [better],
			((' . DB::select([$playerTable, 'pp2' . $key], 'COUNT(*) as [count]')->where(
					'[pp2' . $key . '].%n IN %in',
					'id_game',
					$gameIds
				)->where('[pp2' . $key . '].%n = [p' . $key . '].%n', 'skill', 'skill') . ')-1) as [same]',
			)->join($gameTable, 'g' . $key)->on('[p' . $key . '].[id_game] = [g' . $key . '].[id_game]')->where(
				'[g' . $key . '].%n IN %in',
				'id_game',
				$gameIds
			);
		}
		return (DB::getConnection()->getFluent(
			DB::getConnection()
				->connection
				->select('[p].*, [u].[id_arena], [u].[code]')
				->from(
					'%sql',
					'((' . implode(
						') UNION ALL (',
						$queries
					) . ')) [p]'
				)
				->leftJoin(LigaPlayer::TABLE, 'u')
				->on('[p].[id_user] = [u].[id_user]')
		))->cacheTags(
			'players',
			'arena-players',
			'best-players',
			'leaderboard',
			'arena/' . $arena->id . '/leaderboard/' . $date->format('Y-m-d'),
			'arena/' . $arena->id . '/games/' . $date->format('Y-m-d')
		);
	}

	public function getArenaDateGameCount(Arena $arena, DateTimeInterface $date): int {
		return $arena->queryGames($date)
		             ->cacheTags(
			             'games',
			             'games-' . $date->format('Y-m-d'),
			             'arena/' . $arena->id . '/games/' . $date->format('Y-m-d'),
		             )
		             ->count();
	}

	public function getArenaDatePlayerCount(Arena $arena, DateTimeInterface $date): int {
		return $arena->queryPlayers($date)
		             ->cacheTags(
			             'players',
			             'games-' . $date->format('Y-m-d'),
			             'arena/' . $arena->id . '/games/' . $date->format('Y-m-d'),
		             )
		             ->count();
	}
}