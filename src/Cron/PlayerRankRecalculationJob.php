<?php
declare(strict_types=1);

namespace App\Cron;

use App\Services\Player\Ranking\GameSelection;
use App\Services\Player\Ranking\RankRecalculationQueueService;
use App\Services\Player\Ranking\RankRecalculationService;
use DateTimeImmutable;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class PlayerRankRecalculationJob implements Job
{

	private const int GAME_LIMIT = 200;

	public function __construct(
		private RankRecalculationQueueService $queueService,
		private RankRecalculationService      $recalculationService,
	) {
	}

	public function getName(): string {
		return 'Process queued player rank recalculations';
	}

	public function run(JobLock $lock): void {
		$logger = new Logger(LOG_DIR, 'cron');
		$from = $this->queueService->getOldestUnprocessedDate();
		if ($from === null) {
			$logger->info('No queued player rank recalculations');
			return;
		}

		$summary = $this->recalculationService->recalculateFrom(
			new GameSelection(
				from: $from,
				limit: self::GAME_LIMIT,
			)
		);

		if ($summary->lastProcessedAt !== null) {
			$this->queueService->markProcessedUntil($summary->lastProcessedAt);
		}
		elseif ($summary->gamesProcessed === 0) {
			$this->queueService->markProcessedUntil(new DateTimeImmutable());
		}

		$logger->info(
			sprintf(
				'Processed queued player rank recalculation from %s: %d games, %d rating deltas, %d affected users',
				$from->format('Y-m-d H:i:s'),
				$summary->gamesProcessed,
				$summary->ratingDeltasWritten,
				count($summary->affectedUserIds)
			)
		);
	}

}
