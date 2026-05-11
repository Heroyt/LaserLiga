<?php
declare(strict_types=1);

namespace App\Services\Player\Ranking;

use App\Models\Auth\Player;
use App\Models\DataObjects\Ranking\Calculation\RankDeltaResult;
use App\Models\DataObjects\Ranking\PlayerGameRating;
use DateTimeInterface;
use Dibi\DriverException;
use Dibi\Exception;
use Lsr\Caching\Cache;
use Lsr\Db\DB;

final readonly class DbRatingRepository implements RatingRepository
{

	public function __construct(
		private Cache $cache,
	) {
	}

	public function getPlayerRankOnDate(int $userId, DateTimeInterface $date): int {
		return max(
			0,
			(int)round(
				DB::select('player_game_rating', '100 + SUM([difference])')->where(
					'[id_user] = %i AND [date] < %dt',
					$userId,
					$date
				)->fetchSingle(false) ?? 100
			)
		);
	}

	public function findGameRating(string $code, int $userId): ?PlayerGameRating {
		return DB::select('player_game_rating', '*')
		         ->where('[code] = %s AND [id_user] = %i', $code, $userId)
		         ->fetchDto(PlayerGameRating::class, cache: false);
	}

	public function saveGameRating(RankDeltaResult $rankDelta, string $expectedResultsJson): void {
		$this->runWithDeadlockRetry(function () use ($rankDelta, $expectedResultsJson) {
			$insertData = $this->getInsertData($rankDelta, $expectedResultsJson);
			$exists = DB::select('player_game_rating', 'COUNT(*)')
			            ->where('[code] = %s AND [id_user] = %i', $rankDelta->gameCode, $rankDelta->userId)
			            ->fetchSingle(false);

			if ($exists > 0) {
				DB::update(
					'player_game_rating',
					$insertData,
					['[code] = %s AND [id_user] = %i', $rankDelta->gameCode, $rankDelta->userId]
				);
				return;
			}

			DB::insertIgnore('player_game_rating', $insertData);
		});
	}

	private function runWithDeadlockRetry(callable $callback): void {
		$attempt = 0;
		while (true) {
			try {
				$callback();
				return;
			}
			catch (DriverException $e) {
				if (!$this->isRetryableDeadlock($e) || $attempt >= 3) {
					throw $e;
				}
				$attempt++;
				usleep(100000 * $attempt);
			}
		}
	}

	private function isRetryableDeadlock(DriverException $e): bool {
		return in_array((int)$e->getCode(), [1205, 1213], true);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getInsertData(RankDeltaResult $rankDelta, string $expectedResultsJson): array {
		return [
			'code'             => $rankDelta->gameCode,
			'id_user'          => $rankDelta->userId,
			'difference'       => $rankDelta->difference,
			'date'             => $rankDelta->date,
			'expected_results' => $expectedResultsJson,
			'normalized_skill' => $rankDelta->normalizedSkill,
			'max_skill'        => $rankDelta->maxSkill,
			'min_skill'        => $rankDelta->minSkill,
		];
	}

	public function deleteGameRating(string $code, int $userId): void {
		$this->runWithDeadlockRetry(static function () use ($code, $userId) {
			DB::delete('player_game_rating', ['[code] = %s AND [id_user] = %i', $code, $userId]);
		});
	}

	/**
	 * @param RankDeltaResult[] $rankDeltas
	 * @param array<int, string> $expectedResultsJsonByUserId
	 */
	public function replaceGameRatings(string $code, array $rankDeltas, array $expectedResultsJsonByUserId): void {
		$this->runWithDeadlockRetry(function () use ($code, $rankDeltas, $expectedResultsJsonByUserId) {
			DB::delete('player_game_rating', ['[code] = %s', $code]);
			if (empty($rankDeltas)) {
				return;
			}

			$rows = [];
			foreach ($rankDeltas as $rankDelta) {
				$rows[] = $this->getInsertData(
					$rankDelta,
					$expectedResultsJsonByUserId[$rankDelta->userId] ?? ''
				);
			}

			DB::insert('player_game_rating', ...$rows);
		});
	}

	/**
	 * @param int[]|null $userIds
	 * @throws Exception
	 */
	public function recalculateUserRanks(?array $userIds = null): void {
		if ($userIds === []) {
			return;
		}

		if ($userIds !== null) {
			DB::getConnection()->query(
				"UPDATE %n [a] SET [rank] = 100 + COALESCE((SELECT SUM([b].[difference]) FROM [player_game_rating] [b] WHERE [a].[id_user] = [b].[id_user]),0) WHERE [a].[id_user] IN %in",
				Player::TABLE,
				$userIds
			);
			DB::getConnection()->query(
				"UPDATE %n [a] SET [rank] = 0 WHERE [rank] < 0 AND [a].[id_user] IN %in",
				Player::TABLE,
				$userIds
			);
		}
		else {
			DB::getConnection()->query(
				"UPDATE %n [a] SET [rank] = 100 + COALESCE((SELECT SUM([b].[difference]) FROM [player_game_rating] [b] WHERE [a].[id_user] = [b].[id_user]),0)",
				Player::TABLE
			);
			DB::getConnection()->query(
				"UPDATE %n [a] SET [rank] = 0 WHERE [rank] < 0",
				Player::TABLE
			);
		}

		$this->cache->clean([$this->cache::Tags => [Player::TABLE, Player::TABLE . '/query']]);
	}

}
