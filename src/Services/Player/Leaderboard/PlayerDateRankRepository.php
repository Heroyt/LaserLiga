<?php
declare(strict_types=1);

namespace App\Services\Player\Leaderboard;

use App\Models\DataObjects\Player\Leaderboard\PlayerDateRankRow;
use App\Models\DataObjects\Player\Leaderboard\PlayerRatingDeltaRow;
use DateTimeInterface;

interface PlayerDateRankRepository
{

	/**
	 * @return int[]
	 */
	public function getUserIds(): array;

	/**
	 * @return array<int,float> userId => rating difference sum
	 */
	public function getDeltasBefore(DateTimeInterface $date): array;

	/**
	 * @return PlayerRatingDeltaRow[]
	 */
	public function getDeltas(DateTimeInterface $from, DateTimeInterface $toExclusive): array;

	/**
	 * @param array<string,PlayerDateRankRow[]> $rowsByDate
	 */
	public function replaceDateRanks(array $rowsByDate): void;
}
