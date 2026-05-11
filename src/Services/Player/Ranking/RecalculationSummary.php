<?php
declare(strict_types=1);

namespace App\Services\Player\Ranking;

final readonly class RecalculationSummary
{

	/**
	 * @param int[] $affectedUserIds
	 */
	public function __construct(
		public int   $gamesProcessed,
		public int   $ratingDeltasWritten,
		public array $affectedUserIds,
	) {
	}

}
