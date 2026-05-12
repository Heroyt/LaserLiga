<?php
declare(strict_types=1);

namespace App\Cli\Commands\Players;

use App\Services\Player\Leaderboard\PlayerDateRankRecalculationService;
use App\Services\Player\PlayerRankOrderService;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Db\DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class RecalculateLeaderboardRanksCommand extends Command
{

	public function __construct(
		private readonly PlayerRankOrderService $rankOrderService,
		private readonly PlayerDateRankRecalculationService $recalculationService,
	) {
		parent::__construct();
	}

	public static function getDefaultName(): string {
		return 'players:leaderboard:recalculate-ranks';
	}

	public static function getDefaultDescription(): string {
		return 'Recalculate cached player leaderboard rank positions for a day or date range.';
	}

	protected function configure(): void {
		$this->setAliases(['players:rank:recalculate-date-ranks']);
		$this->addOption('date', null, InputOption::VALUE_REQUIRED, 'Specific date to recalculate, e.g. 2026-05-11');
		$this->addOption('from', null, InputOption::VALUE_REQUIRED, 'Start date for an inclusive range');
		$this->addOption('to', null, InputOption::VALUE_REQUIRED, 'End date for an inclusive range. Defaults to today when --from is used.');
		$this->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of days processed per transaction batch', 30);
		$this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run inside rolled back transactions');
		$this->addOption('compare', null, InputOption::VALUE_NONE, 'Compare current per-day recalculation with optimized range recalculation. Changes are rolled back.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$dates = $this->getDates($input);
		if (empty($dates)) {
			$output->writeln('<error>Missing --date or --from option.</error>');
			return self::INVALID;
		}

		$batchSize = max(1, (int) $input->getOption('batch-size'));
		if ((bool) $input->getOption('compare')) {
			return $this->executeCompare($dates, $batchSize, $output);
		}

		if ($input->getOption('date') === null) {
			return $this->executeOptimizedRange($dates, $batchSize, (bool) $input->getOption('dry-run'), $output);
		}

		$dryRun = (bool) $input->getOption('dry-run');
		$progress = new ProgressBar($output, count($dates));
		$progress->setFormat('debug');
		$progress->start();

		$daysProcessed = 0;
		$rankRowsWritten = 0;
		$lastDate = null;

		foreach (array_chunk($dates, $batchSize) as $batch) {
			DB::begin();
			try {
				foreach ($batch as $date) {
					$ranks = $this->rankOrderService->getDateRanks($date);
					$daysProcessed++;
					$rankRowsWritten += count($ranks);
					$lastDate = $date;
					$progress->advance();
				}

				if ($dryRun) {
					DB::rollback();
				}
				else {
					DB::commit();
				}
			}
			catch (Throwable $e) {
				DB::rollback();
				throw $e;
			}
		}

		$progress->finish();
		$output->writeln('');
		$output->writeln(
			[
				sprintf('<info>Days processed: %d</info>', $daysProcessed),
				sprintf('<info>Leaderboard rank rows written: %d</info>', $rankRowsWritten),
				$lastDate !== null ? sprintf('<info>Last date processed: %s</info>', $lastDate->format('Y-m-d')) : '',
				$dryRun ? '<comment>Dry-run enabled: changes were rolled back.</comment>' : '<info>Changes committed.</info>',
			]
		);

		return self::SUCCESS;
	}

	/**
	 * @return DateTimeImmutable[]
	 */
	private function getDates(InputInterface $input): array {
		$date = $input->getOption('date');
		if (!empty($date)) {
			return [$this->createDate((string) $date)];
		}

		$from = $input->getOption('from');
		if (empty($from)) {
			return [];
		}

		$current = $this->createDate((string) $from);
		$toOption = $input->getOption('to');
		$to = !empty($toOption) ? $this->createDate((string) $toOption) : new DateTimeImmutable('00:00:00');
		if ($current > $to) {
			return [];
		}

		$dates = [];
		$day = new DateInterval('P1D');
		while ($current <= $to) {
			$dates[] = $current;
			$current = $current->add($day);
		}

		return $dates;
	}

	private function createDate(string $date): DateTimeImmutable {
		return (new DateTimeImmutable($date))->setTime(0, 0);
	}

	/**
	 * @param DateTimeImmutable[] $dates
	 */
	private function executeCompare(array $dates, int $batchSize, OutputInterface $output): int {
		$from = $dates[0];
		$to = $dates[array_key_last($dates)];
		$output->writeln(
			sprintf(
				'Comparing current and optimized leaderboard rank recalculation for %s - %s. Changes are rolled back.',
				$from->format('Y-m-d'),
				$to->format('Y-m-d')
			)
		);

		$currentRows = $this->captureCurrentRows($dates, $from, $to);
		$optimizedRows = $this->captureOptimizedRows($from, $to, $batchSize);
		$differences = $this->compareRows($currentRows, $optimizedRows);

		if (empty($differences)) {
			$output->writeln('<info>No leaderboard rank differences found.</info>');
			$output->writeln(sprintf('<info>Compared rows: %d</info>', count($currentRows)));
			return self::SUCCESS;
		}

		$output->writeln(sprintf('<error>Found %d leaderboard rank difference(s).</error>', count($differences)));
		foreach (array_slice($differences, 0, 100) as $difference) {
			$output->writeln($difference);
		}

		return self::FAILURE;
	}

	/**
	 * @param DateTimeImmutable[] $dates
	 *
	 * @return array<string,array{rank:int,position:int,positionText:string}>
	 */
	private function captureCurrentRows(array $dates, DateTimeInterface $from, DateTimeInterface $to): array {
		DB::begin();
		try {
			foreach ($dates as $date) {
				$this->rankOrderService->getDateRanks($date);
			}
			$rows = $this->fetchRows($from, $to);
			DB::rollback();
			return $rows;
		}
		catch (Throwable $e) {
			DB::rollback();
			throw $e;
		}
	}

	/**
	 * @return array<string,array{rank:int,position:int,positionText:string}>
	 */
	private function fetchRows(DateTimeInterface $from, DateTimeInterface $to): array {
		$rows = DB::select(
			'player_date_rank',
			'[id_user] as [userId], [date], [rank], [position], [position_text] as [positionFormatted]'
		)
		          ->where('[date] >= %d AND [date] <= %d', $from, $to)
		          ->orderBy('date')
		          ->asc()
		          ->orderBy('id_user')
		          ->asc()
		          ->fetchAllDto(\App\Models\DataObjects\Player\PlayerRank::class, cache: false);

		$normalized = [];
		foreach ($rows as $row) {
			$normalized[$row->date->format('Y-m-d') . ':' . $row->userId] = [
				'rank'         => $row->rank,
				'position'     => $row->position,
				'positionText' => $row->positionFormatted,
			];
		}

		return $normalized;
	}

	/**
	 * @return array<string,array{rank:int,position:int,positionText:string}>
	 */
	private function captureOptimizedRows(DateTimeInterface $from, DateTimeInterface $to, int $batchSize): array {
		DB::begin();
		try {
			$this->recalculationService->recalculateRange($from, $to, $batchSize);
			$rows = $this->fetchRows($from, $to);
			DB::rollback();
			return $rows;
		}
		catch (Throwable $e) {
			DB::rollback();
			throw $e;
		}
	}

	/**
	 * @param array<string,array{rank:int,position:int,positionText:string}> $currentRows
	 * @param array<string,array{rank:int,position:int,positionText:string}> $optimizedRows
	 *
	 * @return string[]
	 */
	private function compareRows(array $currentRows, array $optimizedRows): array {
		$differences = [];
		foreach (array_unique([...array_keys($currentRows), ...array_keys($optimizedRows)]) as $key) {
			$current = $currentRows[$key] ?? null;
			$optimized = $optimizedRows[$key] ?? null;
			if ($current === $optimized) {
				continue;
			}
			$differences[] = sprintf(
				'%s current=%s optimized=%s',
				$key,
				json_encode($current, JSON_THROW_ON_ERROR),
				json_encode($optimized, JSON_THROW_ON_ERROR)
			);
		}

		return $differences;
	}

	/**
	 * @param DateTimeImmutable[] $dates
	 */
	private function executeOptimizedRange(array $dates, int $batchSize, bool $dryRun, OutputInterface $output): int {
		$from = $dates[0];
		$to = $dates[array_key_last($dates)];
		$start = microtime(true);

		if ($dryRun) {
			DB::begin();
		}

		try {
			$summary = $this->recalculationService->recalculateRange($from, $to, $batchSize);
			if ($dryRun) {
				DB::rollback();
			}
		}
		catch (Throwable $e) {
			if ($dryRun) {
				DB::rollback();
			}
			throw $e;
		}

		$duration = microtime(true) - $start;
		$output->writeln(
			[
				sprintf('<info>Days processed: %d</info>', $summary->daysProcessed),
				sprintf('<info>Leaderboard rank rows written: %d</info>', $summary->rowsWritten),
				sprintf('<info>Date range: %s - %s</info>', $summary->from->format('Y-m-d'), $summary->to->format('Y-m-d')),
				sprintf('<info>Total time: %.3f s</info>', $duration),
				$summary->daysProcessed > 0 ? sprintf('<info>Average time/day: %.3f s</info>', $duration / $summary->daysProcessed) : '',
				$dryRun ? '<comment>Dry-run enabled: changes were rolled back.</comment>' : '<info>Changes committed.</info>',
			]
		);

		return self::SUCCESS;
	}
}
