<?php
declare(strict_types=1);

namespace App\Cli\Commands\Games;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\DataObjects\Game\MinimalGameRow;
use App\Services\Player\Ranking\RankRecalculationService;
use DateTimeImmutable;
use Lsr\Db\DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class RecalculateRanksCommand extends Command
{

	public function __construct(
		private readonly RankRecalculationService $recalculationService,
	) {
		parent::__construct();
	}

	public static function getDefaultName(): string {
		return 'games:rank:recalculate';
	}

	public static function getDefaultDescription(): string {
		return 'Manually recalculate player rank deltas using the SQL DTO recalculation service.';
	}

	protected function configure(): void {
		$this->addOption('game', 'g', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Specific game code');
		$this->addOption('arena', 'a', InputOption::VALUE_REQUIRED, 'Arena ID');
		$this->addOption('player', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Player/user ID');
		$this->addOption('from', null, InputOption::VALUE_REQUIRED, 'Only games starting at or after this datetime');
		$this->addOption('to', null, InputOption::VALUE_REQUIRED, 'Only games starting before this datetime');
		$this->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of games processed per transaction batch', 100);
		$this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run inside rolled back transactions');
		$this->addArgument('offset', InputArgument::OPTIONAL, 'Games DB offset');
		$this->addArgument('limit', InputArgument::OPTIONAL, 'Games DB limit');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$codes = $this->getGameCodes($input);
		if (empty($codes)) {
			$output->writeln('<comment>No matching games found.</comment>');
			return self::SUCCESS;
		}

		$batchSize = max(1, (int)$input->getOption('batch-size'));
		$dryRun = (bool)$input->getOption('dry-run');
		$progress = new ProgressBar($output, count($codes));
		$progress->setFormat('debug');
		$progress->start();

		$gamesProcessed = 0;
		$ratingDeltasWritten = 0;
		$affectedUserIds = [];

		foreach (array_chunk($codes, $batchSize) as $batch) {
			DB::begin();
			try {
				$summary = $this->recalculationService->recalculateGames($batch, true);
				$gamesProcessed += $summary->gamesProcessed;
				$ratingDeltasWritten += $summary->ratingDeltasWritten;
				foreach ($summary->affectedUserIds as $userId) {
					$affectedUserIds[$userId] = $userId;
				}
				foreach ($batch as $_) {
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
				sprintf('<info>Games processed: %d</info>', $gamesProcessed),
				sprintf('<info>Rating deltas written: %d</info>', $ratingDeltasWritten),
				sprintf('<info>Affected users: %d</info>', count($affectedUserIds)),
				$dryRun ? '<comment>Dry-run enabled: changes were rolled back.</comment>' : '<info>Changes committed.</info>',
			]
		);

		return self::SUCCESS;
	}

	/**
	 * @return string[]
	 */
	private function getGameCodes(InputInterface $input): array {
		$games = $input->getOption('game');
		if (is_array($games) && !empty($games)) {
			return array_values(array_unique(array_map('strval', $games)));
		}

		$players = $input->getOption('player');
		if (is_array($players) && !empty($players)) {
			$query = PlayerFactory::queryPlayersWithGames(modeFields: ['rankable'])
			                      ->where('id_user IN %in', array_map('intval', $players))
			                      ->where('rankable = 1')
			                      ->where('id_user IS NOT NULL')
			                      ->groupBy('code');
		}
		else {
			$modes = DB::select(AbstractMode::TABLE, '[id_mode], [name]')
			           ->where('[rankable] = 1')
			           ->cacheTags(AbstractMode::TABLE, 'modes/rankable')
			           ->fetchPairs('id_mode', 'name');

			$query = GameFactory::queryGames(true)
			                    ->where('id_mode IN %in', array_keys($modes))
			                    ->where('%sql', $this->getRegisteredPlayerExistsCondition());
		}

		$query->orderBy('start');

		$limit = $input->getArgument('limit');
		if ($limit !== null) {
			$query->limit((int)$limit);
		}

		$offset = $input->getArgument('offset');
		if ($offset !== null) {
			$query->offset((int)$offset);
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

		$rows = $query->fetchAllDto(MinimalGameRow::class, cache: false);
		return array_values(array_unique(array_map(static fn(MinimalGameRow $row) => $row->code, $rows)));
	}

	private function getRegisteredPlayerExistsCondition(): string {
		$conditions = [];
		foreach (GameFactory::getSupportedSystems() as $i => $system) {
			$conditions[] = sprintf(
				"(`t`.`system` = '%s' AND EXISTS (SELECT 1 FROM `%s_players` `rp%d` WHERE `rp%d`.`id_game` = `t`.`id_game` AND `rp%d`.`id_user` IS NOT NULL))",
				$system,
				$system,
				$i,
				$i,
				$i
			);
		}

		return '(' . implode(' OR ', $conditions) . ')';
	}

}
