<?php
declare(strict_types=1);

namespace App\Cli\Commands\Games;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\DataObjects\Game\MinimalGameRow;
use App\Services\Player\RankCalculator;
use DateTimeImmutable;
use Dibi\Exception;
use Lsr\Db\DB;
use Lsr\Orm\ModelRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class BenchmarkRankRecalculationCommand extends Command
{

	public function __construct(
		private readonly RankCalculator $rankCalculator,
	) {
		parent::__construct();
	}

	public static function getDefaultName(): string {
		return 'games:rank:benchmark';
	}

	public static function getDefaultDescription(): string {
		return 'Benchmark player rank recalculation on real games without persisting changes.';
	}

	protected function configure(): void {
		$this->addArgument('offset', InputArgument::OPTIONAL, 'Games DB offset', 0);
		$this->addArgument('limit', InputArgument::OPTIONAL, 'Games DB limit', 50);
		$this->addOption('game', 'g', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Specific game code');
		$this->addOption('arena', 'a', InputOption::VALUE_REQUIRED, 'Arena ID');
		$this->addOption('from', null, InputOption::VALUE_REQUIRED, 'Only games starting at or after this datetime');
		$this->addOption('to', null, InputOption::VALUE_REQUIRED, 'Only games starting before this datetime');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$rows = $this->getGameRows($input);
		if (empty($rows)) {
			$output->writeln('<comment>No games matched the benchmark filters.</comment>');
			return self::SUCCESS;
		}

		$output->writeln(
			sprintf(
				'Benchmarking rank recalculation for %d game(s). Changes are rolled back.',
				count($rows)
			)
		);

		$startedAt = microtime(true);
		$peakBefore = memory_get_peak_usage(true);
		$results = [];
		$totalPlayers = 0;
		$totalRegisteredPlayers = 0;

		DB::begin();
		try {
			foreach ($rows as $i => $row) {
				$game = GameFactory::getByCode($row->code);
				if (!isset($game)) {
					$results[] = [$i + 1, $row->code, 'missing', '-', '-', '-'];
					continue;
				}

				$players = $game->players->getAll();
				$playerCount = count($players);
				$registeredPlayerCount = $this->countRegisteredPlayers($players);
				$totalPlayers += $playerCount;
				$totalRegisteredPlayers += $registeredPlayerCount;

				$gameStartedAt = microtime(true);
				$this->rankCalculator->recalculateRatingForGame($game);
				$duration = microtime(true) - $gameStartedAt;

				$results[] = [
					$i + 1,
					$game->code,
					$game->start?->format('Y-m-d H:i:s') ?? '-',
					$playerCount,
					$registeredPlayerCount,
					sprintf('%.3f s', $duration),
				];

				ModelRepository::removeInstance($game);
				unset($game, $players);
			}
		}
		catch (Throwable $e) {
			DB::rollback();
			throw $e;
		}
		DB::rollback();

		$totalDuration = microtime(true) - $startedAt;
		$averageDuration = $totalDuration / count($rows);
		$peakMemory = memory_get_peak_usage(true) - $peakBefore;

		(new Table($output))
			->setHeaders(['#', 'Code', 'Start', 'Players', 'Registered', 'Duration'])
			->setRows($results)
			->render();

		$output->writeln(
			[
				sprintf('Total time: %.3f s', $totalDuration),
				sprintf('Average time/game: %.3f s', $averageDuration),
				sprintf('Games/minute estimate: %.1f', $averageDuration > 0.0 ? 60 / $averageDuration : 0),
				sprintf('Players processed: %d (%d registered)', $totalPlayers, $totalRegisteredPlayers),
				sprintf('Additional peak memory: %.2f MiB', $peakMemory / 1024 / 1024),
			]
		);

		return self::SUCCESS;
	}

	/**
	 * @return array<int, MinimalGameRow|object{code:string}>
	 * @throws Exception
	 */
	private function getGameRows(InputInterface $input): array {
		$games = $input->getOption('game');
		if (is_array($games) && !empty($games)) {
			$rows = [];
			foreach ($games as $code) {
				$rows[] = (object) ['code' => (string) $code];
			}
			return $rows;
		}

		$modes = DB::select(AbstractMode::TABLE, '[id_mode], [name]')
		           ->where('[rankable] = 1')
		           ->cacheTags(AbstractMode::TABLE, 'modes/rankable')
		           ->fetchPairs('id_mode', 'name');

		$query = GameFactory::queryGames(true)
		                    ->where('id_mode IN %in', array_keys($modes))
		                    ->where('%sql', $this->getRegisteredPlayerExistsCondition())
		                    ->orderBy('start')
		                    ->limit((int)$input->getArgument('limit'))
		                    ->offset((int)$input->getArgument('offset'));

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

		return $query->fetchAllDto(MinimalGameRow::class, cache: false);
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

	/**
	 * @param array<int, mixed> $players
	 */
	private function countRegisteredPlayers(array $players): int {
		$count = 0;
		foreach ($players as $player) {
			if (isset($player->user)) {
				$count++;
			}
		}
		return $count;
	}

}
