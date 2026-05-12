<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Player\Leaderboard;

use App\Models\DataObjects\Player\PlayerRank;
use DateTimeInterface;

final readonly class PlayerDateRankRecalculationSummary
{

	/**
	 * @param int[]                 $affectedUserIds
	 * @param array<int,PlayerRank> $lastRanks
	 */
	public function __construct(
		public int $daysProcessed,
		public int $rowsWritten,
		public DateTimeInterface $from,
		public DateTimeInterface $to,
		public array $affectedUserIds = [],
		public array $lastRanks = [],
	) {
	}
}
