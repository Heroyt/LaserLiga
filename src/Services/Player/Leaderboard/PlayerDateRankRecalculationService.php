<?php
declare(strict_types=1);

namespace App\Services\Player\Leaderboard;

use App\Models\DataObjects\Player\Leaderboard\PlayerDateRankRecalculationSummary;
use App\Models\DataObjects\Player\PlayerRank;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

final readonly class PlayerDateRankRecalculationService
{

	public function __construct(
		private PlayerDateRankRepository $repository,
		private PlayerDateRankPositionCalculator $positionCalculator,
	) {
	}

	/**
	 * @return array<int,PlayerRank>
	 */
	public function recalculateDate(DateTimeInterface $date): array {
		return $this->recalculateRange($date, $date)->lastRanks;
	}

	public function recalculateRange(
		DateTimeInterface $from,
		DateTimeInterface $to,
		int $batchSize = 30,
	): PlayerDateRankRecalculationSummary {
		$from = $this->createDate($from);
		$to = $this->createDate($to);
		if ($from > $to) {
			return new PlayerDateRankRecalculationSummary(0, 0, $from, $to);
		}

		$batchSize = max(1, $batchSize);
		$rankState = $this->createInitialRankState($from);
		$deltasByDate = $this->groupDeltasByDate($from, $to->add(new DateInterval('P1D')));

		$pendingRows = [];
		$affectedUserIds = [];
		$daysProcessed = 0;
		$rowsWritten = 0;
		$lastRanks = [];
		$day = new DateInterval('P1D');
		$current = $from;

		while ($current <= $to) {
			$dateString = $current->format('Y-m-d');
			foreach ($deltasByDate[$dateString] ?? [] as $delta) {
				if (!isset($rankState[$delta->userId])) {
					continue;
				}
				$rankState[$delta->userId] += $delta->difference;
				$affectedUserIds[$delta->userId] = $delta->userId;
			}

			$rows = $this->positionCalculator->calculateRows($current, $rankState);
			$pendingRows[$dateString] = $rows;
			$daysProcessed++;
			$rowsWritten += count($rows);
			$lastRanks = $this->rowsToPlayerRanks($rows);

			if (count($pendingRows) >= $batchSize) {
				$this->repository->replaceDateRanks($pendingRows);
				$pendingRows = [];
			}

			$current = $current->add($day);
		}

		$this->repository->replaceDateRanks($pendingRows);

		return new PlayerDateRankRecalculationSummary(
			$daysProcessed,
			$rowsWritten,
			$from,
			$to,
			array_values($affectedUserIds),
			$lastRanks
		);
	}

	private function createDate(DateTimeInterface $date): DateTimeImmutable {
		return DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
	}

	/**
	 * @return array<int,float>
	 */
	private function createInitialRankState(DateTimeInterface $from): array {
		$rankState = [];
		foreach ($this->repository->getUserIds() as $userId) {
			$rankState[$userId] = 100.0;
		}

		foreach ($this->repository->getDeltasBefore($from) as $userId => $difference) {
			if (!isset($rankState[$userId])) {
				continue;
			}
			$rankState[$userId] = 100.0 + $difference;
		}

		return $rankState;
	}

	/**
	 * @return array<string,array<int,object{userId:int,date:DateTimeInterface,difference:float}>>
	 */
	private function groupDeltasByDate(DateTimeInterface $from, DateTimeInterface $toExclusive): array {
		$deltasByDate = [];
		foreach ($this->repository->getDeltas($from, $toExclusive) as $delta) {
			$dateString = $delta->date->format('Y-m-d');
			$deltasByDate[$dateString][] = $delta;
		}
		return $deltasByDate;
	}

	/**
	 * @param array<int,\App\Models\DataObjects\Player\Leaderboard\PlayerDateRankRow> $rows
	 *
	 * @return array<int,PlayerRank>
	 */
	private function rowsToPlayerRanks(array $rows): array {
		$ranks = [];
		foreach ($rows as $row) {
			$ranks[$row->userId] = $row->toPlayerRank();
		}
		return $ranks;
	}
}
