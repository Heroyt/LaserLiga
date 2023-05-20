<?php

namespace App\Services;

use App\Models\Auth\LigaPlayer;
use App\Models\DataObjects\PlayerRank;
use DateTimeInterface;
use Dibi\Exception;
use InvalidArgumentException;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;

class PlayerRankOrderService
{

	/** @var PlayerRank[] */
	private array $todayRanksByUserId = [];
	/** @var PlayerRank[][] */
	private array $todayRanksByPosition = [];

	public function __construct(
		private readonly Cache $cache,
	) {
	}

	/**
	 * @param LigaPlayer $player
	 * @param DateTimeInterface $date
	 * @return PlayerRank
	 * @throws Exception
	 */
	public function getDateRankForPlayer(LigaPlayer $player, DateTimeInterface $date): PlayerRank {
		if (!isset($player->id)) {
			throw new InvalidArgumentException('Invalid player');
		}

		$dateString = $date->format('Y-m-d');
		$row = DB::select('player_date_rank', '*')
						 ->where('id_user = %i AND [date] = %d', $player->id, $date)
						 ->cacheTags('date_rank', 'date_rank_' . $dateString)
						 ->fetch();
		if (isset($row)) {
			return new PlayerRank($row->id_user, $date, $row->rank, $row->position, $row->position_text);
		}

		return
			$this->getDateRanks($date)[$player->id] ??
			PlayerRank::create(
				[
					'id_user' => $player->id,
					'date' => $dateString,
					'rank' => $player->stats->rank,
					'position' => 0,
					'position_text' => '0.',
				]
			);
	}

	/**
	 * @param DateTimeInterface $date
	 * @return array<int,PlayerRank>
	 * @throws Exception
	 */
	public function getDateRanks(DateTimeInterface $date): array {
		/** @var array<int,int> $ranks */
		$ranks = DB::select
		(
			['players', 'b'],
			'b.id_user, ROUND(100 + COALESCE(%sql,0)) as rank',
			DB::select(['player_game_rating', 'a'], 'SUM(a.difference)')
				->where('a.id_user = b.id_user AND DATE([a.date]) <= %d', $date)
				->fluent
		)
							 ->orderBy('rank')
							 ->desc()
							 ->fetchPairs('id_user', 'rank', false);

		$dateString = $date->format('Y-m-d');
		$rows = [];

		$order = 0;
		$realOrder = 0;
		$lastRank = 0;
		$sameRank = 0;
		foreach ($ranks as $id => $rank) {
			$realOrder++;
			if ($lastRank !== $rank) {
				if ($sameRank > 0) {
					$rowCount = count($rows);
					for ($i = $rowCount - $sameRank - 1; $i < $rowCount; $i++) {
						$rows[$i]['position_text'] = $order . '-' . ($order + $sameRank) . '.';
					}
				}

				$sameRank = 0;
				$order = $realOrder;
				$lastRank = $rank;
			}
			else {
				$sameRank++;
			}
			$rows[] = [
				'id_user' => $id,
				'date' => $dateString,
				'rank' => $rank,
				'position' => $order,
				'position_text' => $order . '.',
			];
		}

		DB::replace('player_date_rank', $rows);
		$this->cache->clean([
			Cache::Tags => ['date_rank', 'date_rank_' . $dateString],
		]);

		$newRows = [];
		foreach ($rows as $row) {
			$newRows[$row['id_user']] = PlayerRank::create($row);
		}

		return $newRows;
	}

	/**
	 * @param int $position
	 * @return PlayerRank[]
	 * @throws Exception
	 */
	public function getTodayRanksForPosition(int $position): array {
		$ranks = $this->getTodayRanksByPosition();
		while (!isset($ranks[$position]) && $position > 0) {
			$position--;
		}
		return $ranks[$position] ?? [];
	}

	/**
	 * @return PlayerRank[][]
	 * @throws Exception
	 */
	public function getTodayRanksByPosition(): array {
		if (empty($this->todayRanksByPosition)) {
			$this->getTodayRanks();
		}
		return $this->todayRanksByPosition;
	}

	/**
	 * @return PlayerRank[]
	 * @throws Exception
	 */
	public function getTodayRanks(): array {
		if (!empty($this->todayRanksByUserId)) {
			return $this->todayRanksByUserId;
		}

		$today = new \DateTimeImmutable('00:00:00');

		$rows = DB::select('player_date_rank', '*')
							->where('[date] = %d', $today)
							->orderBy('position')
							->desc()
							->cacheTags('date_rank', 'date_rank_' . $today->format('Y-m-d'))
							->fetchAssoc('id_user');

		if (empty($rows)) {
			$rows = $this->getDateRanks($today);
		}

		foreach ($rows as $id => $row) {
			if (!($row instanceof PlayerRank)) {
				$row = PlayerRank::create($row);
			}

			$this->todayRanksByUserId[$id] = $row;
			if (!isset($this->todayRanksByPosition[$row->position])) {
				$this->todayRanksByPosition[$row->position] = [];
			}
			$this->todayRanksByPosition[$row->position][] = $row;
		}

		return $this->todayRanksByUserId;
	}
}