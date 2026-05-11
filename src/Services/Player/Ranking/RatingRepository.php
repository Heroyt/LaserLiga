<?php
declare(strict_types=1);

namespace App\Services\Player\Ranking;

use App\Models\DataObjects\Ranking\Calculation\RankDeltaResult;
use App\Models\DataObjects\Ranking\PlayerGameRating;
use DateTimeInterface;

interface RatingRepository
{

	public function getPlayerRankOnDate(int $userId, DateTimeInterface $date): int;

	public function findGameRating(string $code, int $userId): ?PlayerGameRating;

	public function saveGameRating(RankDeltaResult $rankDelta, string $expectedResultsJson): void;

	public function deleteGameRating(string $code, int $userId): void;

	/**
	 * @param RankDeltaResult[] $rankDeltas
	 * @param array<int, string> $expectedResultsJsonByUserId
	 */
	public function replaceGameRatings(string $code, array $rankDeltas, array $expectedResultsJsonByUserId): void;

	/**
	 * @param int[]|null $userIds
	 */
	public function recalculateUserRanks(?array $userIds = null): void;

}
