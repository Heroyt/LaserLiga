<?php
declare(strict_types=1);

namespace App\Cli\Commands;

use App\Models\Arena;
use App\Services\ArenaStatsAggregator;
use DateTimeImmutable;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ArenaReport extends Command
{

	private const array VALID_REPORT_TYPES = [
		'games',
		'players',
		'daily-players',
	];

	public function __construct(
		readonly private ArenaStatsAggregator $arenaStatsAggregator,
		?string                               $name = null
	) {
		parent::__construct($name);
	}

	public static function getDefaultName(): ?string {
		return 'arena:report';
	}

	public static function getDefaultDescription(): ?string {
		return 'Report arena stats';
	}

	protected function configure(): void {
		$this->addArgument('arenaId', InputArgument::REQUIRED, 'Arena ID');
		$this->addArgument(
			'reportType',
			InputArgument::REQUIRED,
			'Report type - ' . implode(', ', self::VALID_REPORT_TYPES),
			null,
			self::VALID_REPORT_TYPES,
		);

		$this->addOption(
			'date',
			'd',
			InputArgument::OPTIONAL,
			'Date for the report (YYYY-MM-DD) or a date range (YYYY-MM-DD:YYYY-MM-DD)'
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$arenaId = (int)$input->getArgument('arenaId');
		try {
			$arena = Arena::get($arenaId);
		} catch (ModelNotFoundException $e) {
			$output->writeln('<error>Invalid arena ID</error>');
			return self::FAILURE;
		}

		$output->writeln(sprintf('<info>Generating report for arena: %s (%d)</info>', $arena->name, $arena->id));

		$reportType = strtolower($input->getArgument('reportType'));
		if (!in_array($reportType, self::VALID_REPORT_TYPES, true)) {
			$output->writeln(
				'<error>Invalid report type. Valid types are: ' . implode(', ', self::VALID_REPORT_TYPES) . '</error>'
			);
			return self::FAILURE;
		}

		$dateInput = $input->getOption('date');
		if (empty($dateInput)) {
			return $this->doSingleDateReport($arena, $reportType, new DateTimeImmutable(), $output);
		}

		$dateParts = explode(':', $dateInput);
		if (count($dateParts) === 1) {
			$date = DateTimeImmutable::createFromFormat('Y-m-d', $dateParts[0]);
			if ($date === false) {
				$output->writeln('<error>Invalid date format. Use YYYY-MM-DD.</error>');
				return self::FAILURE;
			}
			return $this->doSingleDateReport($arena, $reportType, $date, $output);
		}

		if (count($dateParts) !== 2) {
			$output->writeln('<error>Invalid date range format. Use YYYY-MM-DD-YYYY-MM-DD.</error>');
			return self::FAILURE;
		}

		$dateFrom = DateTimeImmutable::createFromFormat('Y-m-d', $dateParts[0]);
		$dateTo = DateTimeImmutable::createFromFormat('Y-m-d', $dateParts[1]);
		if ($dateFrom === false || $dateTo === false) {
			$output->writeln('<error>Invalid date format. Use YYYY-MM-DD:YYYY-MM-DD.</error>');
			return self::FAILURE;
		}
		if ($dateFrom > $dateTo) {
			$output->writeln('<error>Date range is invalid: From date is after To date.</error>');
			return self::FAILURE;
		}
		if ($dateFrom->format('Y-m-d') === $dateTo->format('Y-m-d')) {
			return $this->doSingleDateReport($arena, $reportType, $dateFrom, $output);
		}
		return $this->doDateRangeReport($arena, $reportType, $dateFrom, $dateTo, $output);
	}

	private function doSingleDateReport(Arena $arena, string $reportType, DateTimeImmutable $date, OutputInterface $output): int {
		switch ($reportType) {
			case 'games':
				$gamesCount = $this->arenaStatsAggregator->getArenaDateGameCount($arena, $date);
				$output->writeln(sprintf('Games on %s: %d', $date->format('Y-m-d'), $gamesCount));
				break;

			case 'players':
				$playersCount = $this->arenaStatsAggregator->getArenaDatePlayerCount($arena, $date);
				$output->writeln(sprintf('Players on %s: %d', $date->format('Y-m-d'), $playersCount));
				break;

			default:
				$output->writeln('<error>Invalid report type for single date</error>');
				return self::FAILURE;
		}


		return self::SUCCESS;
	}

	private function doDateRangeReport(Arena $arena, string $reportType, DateTimeImmutable $dateFrom, DateTimeImmutable $dateTo, OutputInterface $output): int {
		switch ($reportType) {
			case 'games':
				$gamesCount = $this->arenaStatsAggregator->getArenaGameCount($arena, $dateFrom, $dateTo);
				$output->writeln(sprintf('Games from %s to %s: %d', $dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d'), $gamesCount));
				break;

			case 'players':
				$playersCount = $this->arenaStatsAggregator->getArenaPlayerCount($arena, $dateFrom, $dateTo);
				$output->writeln(sprintf('Players from %s to %s: %d', $dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d'), $playersCount));
				break;

			case 'daily-players':
				$dailyPlayers = $this->arenaStatsAggregator->getArenaPlayerCounts($arena, $dateFrom, $dateTo);
				foreach ($dailyPlayers as $date => $count) {
					$output->writeln(sprintf('Players on %s: %d', $date, $count));
				}
				break;

			default:
				$output->writeln('<error>Invalid report type for date range</error>');
				return self::FAILURE;
		}

		return self::SUCCESS;
	}

}