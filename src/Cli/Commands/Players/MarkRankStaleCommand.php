<?php
declare(strict_types=1);

namespace App\Cli\Commands\Players;

use App\GameModels\Factory\PlayerFactory;
use App\Services\Player\Ranking\RankRecalculationQueueService;
use DateTimeImmutable;
use DateTimeInterface;
use Dibi\Row;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MarkRankStaleCommand extends Command
{

	public function __construct(
		private readonly RankRecalculationQueueService $queueService,
	) {
		parent::__construct();
	}

	public static function getDefaultName(): string {
		return 'players:rank:mark-stale';
	}

	public static function getDefaultDescription(): string {
		return 'Mark player games as stale for queued rank recalculation.';
	}

	protected function configure(): void {
		$this->addOption('player', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Player/user ID');
		$this->addOption('arena', 'a', InputOption::VALUE_REQUIRED, 'Arena ID');
		$this->addOption('from', null, InputOption::VALUE_REQUIRED, 'Only games starting at or after this datetime');
		$this->addOption('to', null, InputOption::VALUE_REQUIRED, 'Only games starting before this datetime');
		$this->addOption('reason', 'r', InputOption::VALUE_REQUIRED, 'Queue reason', 'manual');
		$this->addArgument('offset', InputArgument::OPTIONAL, 'Games DB offset', 0);
		$this->addArgument('limit', InputArgument::OPTIONAL, 'Games DB limit', 200);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$rows = $this->getRows($input);
		if (empty($rows)) {
			$output->writeln('<comment>No matching player games found.</comment>');
			return self::SUCCESS;
		}

		$progress = new ProgressBar($output, count($rows));
		$progress->setFormat('debug');
		$progress->start();

		foreach ($rows as $row) {
			$this->queueService->markDirty(
				$this->normalizeDate($row->start),
				(string)$input->getOption('reason'),
				(string)$row->code,
				isset($row->id_user) ? (int)$row->id_user : null
			);
			$progress->advance();
		}

		$progress->finish();
		$output->writeln('');
		$output->writeln(sprintf('<info>Marked %d player game(s) as stale.</info>', count($rows)));

		return self::SUCCESS;
	}

	/**
	 * @return Row[]
	 */
	private function getRows(InputInterface $input): array {
		$query = PlayerFactory::queryPlayersWithGames(modeFields: ['rankable'])
		                      ->where('id_user IS NOT NULL')
		                      ->where('rankable = 1')
		                      ->orderBy('start')
		                      ->limit((int)$input->getArgument('limit'))
		                      ->offset((int)$input->getArgument('offset'));

		$players = $input->getOption('player');
		if (is_array($players) && !empty($players)) {
			$query->where('id_user IN %in', array_map('intval', $players));
		}

		$arenaId = $input->getOption('arena');
		if (!empty($arenaId)) {
			$query->where('id_arena = %i', (int)$arenaId);
		}

		$from = $input->getOption('from');
		if (!empty($from)) {
			$query->where('start >= %dt', new DateTimeImmutable((string)$from));
		}

		$to = $input->getOption('to');
		if (!empty($to)) {
			$query->where('start < %dt', new DateTimeImmutable((string)$to));
		}

		return $query->fetchAll(cache: false);
	}

	private function normalizeDate(mixed $date): DateTimeInterface {
		if ($date instanceof DateTimeInterface) {
			return $date;
		}
		return new DateTimeImmutable((string)$date);
	}

}
