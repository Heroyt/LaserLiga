<?php
declare(strict_types=1);

namespace App\Services\Player\Leaderboard;

use App\Models\DataObjects\Player\Leaderboard\PlayerDateRankRow;
use DateTimeInterface;

final readonly class PlayerDateRankPositionCalculator
{

	/**
	 * @param array<int,int|float> $ranks userId => rank
	 *
	 * @return PlayerDateRankRow[]
	 */
	public function calculateRows(DateTimeInterface $date, array $ranks): array {
		arsort($ranks);

		$rows = [];
		$order = 0;
		$realOrder = 0;
		$lastRank = null;
		$sameRank = 0;

		foreach ($ranks as $userId => $rank) {
			$rank = (int) round((float) $rank);
			$realOrder++;
			if ($lastRank !== $rank) {
				if ($sameRank > 0) {
					$rowCount = count($rows);
					for ($i = $rowCount - $sameRank - 1; $i < $rowCount; $i++) {
						$row = $rows[$i];
						$rows[$i] = new PlayerDateRankRow(
							$row->userId,
							$row->date,
							$row->rank,
							$row->position,
							$order . '-' . ($order + $sameRank) . '.'
						);
					}
				}

				$sameRank = 0;
				$order = $realOrder;
				$lastRank = $rank;
			}
			else {
				$sameRank++;
			}

			$rows[] = new PlayerDateRankRow(
				(int) $userId,
				$date,
				$rank,
				$order,
				$order . '.'
			);
		}

		return $rows;
	}
}
