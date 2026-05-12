<?php
declare(strict_types=1);

namespace App\Services\Player\Leaderboard;

use App\Models\DataObjects\Player\Leaderboard\PlayerDateRankRow;
use App\Models\DataObjects\Player\Leaderboard\PlayerRatingDeltaRow;
use DateTimeInterface;
use Lsr\Caching\Cache;
use Lsr\Db\DB;

final readonly class DbPlayerDateRankRepository implements PlayerDateRankRepository
{

	public function __construct(
		private Cache $cache,
	) {
	}

	/**
	 * @return int[]
	 */
	public function getUserIds(): array {
		return array_map(
			'intval',
			DB::select('players', '[id_user]')
			  ->where('[id_user] IS NOT NULL')
			  ->fetchPairs('id_user', 'id_user', false)
		);
	}

	/**
	 * @return array<int,float>
	 */
	public function getDeltasBefore(DateTimeInterface $date): array {
		/** @var array<int,float|int|string> $rows */
		$rows = DB::select('player_game_rating', '[id_user], SUM([difference]) as [difference]')
		          ->where('[date] < %dt', $date)
		          ->groupBy('id_user')
		          ->fetchPairs('id_user', 'difference', false);

		$deltas = [];
		foreach ($rows as $userId => $difference) {
			$deltas[(int) $userId] = (float) $difference;
		}

		return $deltas;
	}

	/**
	 * @return PlayerRatingDeltaRow[]
	 */
	public function getDeltas(DateTimeInterface $from, DateTimeInterface $toExclusive): array {
		return DB::select(
			'player_game_rating',
			'[id_user] as [userId], [date], [difference]'
		)
		         ->where('[date] >= %dt AND [date] < %dt', $from, $toExclusive)
		         ->orderBy('date')
		         ->asc()
		         ->fetchAllDto(PlayerRatingDeltaRow::class, cache: false);
	}

	/**
	 * @param array<string,PlayerDateRankRow[]> $rowsByDate
	 */
	public function replaceDateRanks(array $rowsByDate): void {
		if (empty($rowsByDate)) {
			return;
		}

		$rows = [];
		foreach ($rowsByDate as $dateRows) {
			foreach ($dateRows as $row) {
				$rows[] = $row->toArray();
			}
		}

		if (!empty($rows)) {
			DB::replace('player_date_rank', $rows);
		}

		$tags = ['date_rank'];
		foreach (array_keys($rowsByDate) as $dateString) {
			$tags[] = 'date_rank_' . $dateString;
		}

		$this->cache->clean([
			$this->cache::Tags => $tags,
		]);
	}
}
