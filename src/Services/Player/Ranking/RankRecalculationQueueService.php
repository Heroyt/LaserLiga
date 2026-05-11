<?php
declare(strict_types=1);

namespace App\Services\Player\Ranking;

use App\Models\PlayerRankRecalculationQueue;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Db\DB;

final readonly class RankRecalculationQueueService
{

	public const string REASON_PLAYER_GAME_LINKED = 'player_game_linked';
	public const string REASON_PLAYER_GAME_REMOVED = 'player_game_removed';

	public function markDirty(
		DateTimeInterface $recalculateFrom,
		string $reason,
		?string $code = null,
		?int $userId = null,
	): void {
		DB::insert(PlayerRankRecalculationQueue::TABLE, [
			'recalculate_from' => $recalculateFrom,
			'reason'           => $reason,
			'code'             => $code,
			'id_user'          => $userId,
		]);
	}

	public function getOldestUnprocessedDate(): ?DateTimeInterface {
		$date = DB::select(PlayerRankRecalculationQueue::TABLE, 'MIN([recalculate_from])')
		          ->where('[processed_at] IS NULL')
		          ->fetchSingle(false);
		if ($date === null || $date instanceof DateTimeInterface) {
			return $date;
		}
		return new DateTimeImmutable((string)$date);
	}

	public function markProcessedUntil(DateTimeInterface $processedUntil): void {
		DB::update(
			PlayerRankRecalculationQueue::TABLE,
			['processed_at' => new DateTimeImmutable()],
			['[processed_at] IS NULL AND [recalculate_from] <= %dt', $processedUntil]
		);
	}

}
