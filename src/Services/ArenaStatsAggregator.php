<?php
declare(strict_types=1);

namespace App\Services;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\DataObjects\Game\LeaderboardRecord;
use DateTimeInterface;
use Dibi\Row;
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

	public function getArenaGameCount(Arena $arena, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): int {
		return $arena->queryGames()
		             ->where('DATE([start]) BETWEEN %d AND %d', $dateFrom, $dateTo)
		             ->cacheTags(
			             'games',
			             'games-' . $dateFrom->format('Y-m-d') . '-' . $dateTo->format('Y-m-d'),
			             'arena/' . $arena->id . '/games/' . $dateFrom->format('Y-m-d') . '/' . $dateTo->format(
				             'Y-m-d'
			             ),
		             )
		             ->count();
	}

	public function getArenaPlayerCount(Arena $arena, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): int {
		$query = $arena->queryGames()
		               ->where('DATE([start]) BETWEEN %d AND %d', $dateFrom, $dateTo)
		               ->cacheTags(
			               'games',
			               'games-' . $dateFrom->format('Y-m-d') . '-' . $dateTo->format('Y-m-d'),
			               'arena/' . $arena->id . '/games/' . $dateFrom->format('Y-m-d') . '/' . $dateTo->format(
				               'Y-m-d'
			               ),
		               );
		/** @var array<string,array<int,Row>> $rows */
		$rows = $query->fetchAssoc('system|id_game');
		$gameIds = array_map(static fn($games) => array_keys($games), $rows);

		return PlayerFactory::queryPlayers($gameIds)
		                    ->cacheTags(
			                    'players',
			                    'games-' . $dateFrom->format('Y-m-d') . '-' . $dateTo->format('Y-m-d'),
			                    'arena/' . $arena->id . '/games/' . $dateFrom->format('Y-m-d') . '/' . $dateTo->format(
				                    'Y-m-d'
			                    ),
		                    )
		                    ->count();
	}

	/**
	 * Get player counts for each day in the given date range.
	 *
	 * @return array<string, int>
	 */
	public function getArenaPlayerCounts(Arena $arena, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array {
		$counts = [];
		$rows = DB::select(null, 'DATE([start]) AS [date], COUNT(*) AS [count]')
		            ->from(
			            '%sql as [players]',
			            PlayerFactory::queryPlayersWithGames()
				            ->where('id_arena = %i', $arena->id)
				            ->where('DATE([start]) BETWEEN %d AND %d', $dateFrom, $dateTo)
		            )
		            ->groupBy('DATE([start])')
		            ->cacheTags(
			            'players',
			            'games-' . $dateFrom->format('Y-m-d') . '-' . $dateTo->format('Y-m-d'),
			            'arena/' . $arena->id . '/games/' . $dateFrom->format('Y-m-d') . '/' . $dateTo->format(
				            'Y-m-d'
			            ),
		            )
		            ->fetchAll();
		$date = $dateFrom;
		while ($date <= $dateTo) {
			$dateStr = $date->format('Y-m-d');
			$counts[$dateStr] = 0;
			$date = $date->modify('+1 day');
		}
		foreach($rows as $row) {
			$dateStr = $row->date->format('Y-m-d');
			$counts[$dateStr] = (int)$row->count;
		}
		return $counts;
	}

	/**
	 * Get player counts for each day in the given date range.
	 *
	 * @return array<string, int>
	 */
	public function getArenaGameCounts(Arena $arena, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array {
		$counts = [];
		$rows = DB::select(null, 'DATE([start]) AS [date], COUNT(*) AS [count]')
		            ->from(
			            '%sql as [players]',
			            $arena->queryGames()->where('DATE([start]) BETWEEN %d AND %d', $dateFrom, $dateTo)
		            )
		            ->groupBy('DATE([start])')
		            ->cacheTags(
			            'players',
			            'games-' . $dateFrom->format('Y-m-d') . '-' . $dateTo->format('Y-m-d'),
			            'arena/' . $arena->id . '/games/' . $dateFrom->format('Y-m-d') . '/' . $dateTo->format(
				            'Y-m-d'
			            ),
		            )
		            ->fetchAll();
		$date = $dateFrom;
		while ($date <= $dateTo) {
			$dateStr = $date->format('Y-m-d');
			$counts[$dateStr] = 0;
			$date = $date->modify('+1 day');
		}
		foreach($rows as $row) {
			$dateStr = $row->date->format('Y-m-d');
			$counts[$dateStr] = (int)$row->count;
		}
		return $counts;
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