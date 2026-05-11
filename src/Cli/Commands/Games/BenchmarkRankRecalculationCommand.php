<?php
declare(strict_types=1);

namespace App\Cli\Commands\Games;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\DataObjects\Game\MinimalGameRow;
use App\Services\Player\RankCalculator;
use App\Services\Player\Ranking\GameSelection;
use App\Services\Player\Ranking\RankRecalculationService;
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
		private readonly RankCalculator           $rankCalculator,
		private readonly RankRecalculationService $rankRecalculationService,
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
		$this->addOption('service', null, InputOption::VALUE_NONE, 'Use the new SQL DTO recalculation service');
		$this->addOption('compare', null, InputOption::VALUE_NONE, 'Compare model recalculation output with SQL DTO service output');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ($input->getOption('compare')) {
			return $this->executeCompare($input, $output);
		}
		if ($input->getOption('service')) {
			return $this->executeServiceBenchmark($input, $output);
		}

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

	private function executeCompare(InputInterface $input, OutputInterface $output): int {
		$rows = $this->getGameRows($input);
		if (empty($rows)) {
			$output->writeln('<comment>No games matched the compare filters.</comment>');
			return self::SUCCESS;
		}

		$codes = array_values(array_unique(array_map(static fn($row) => $row->code, $rows)));
		$output->writeln(sprintf('Comparing model and SQL DTO rank recalculation for %d game(s). Changes are rolled back.', count($codes)));

		$modelRatings = $this->captureModelRatings($rows, $codes);
		$serviceRatings = $this->captureServiceRatings($codes);
		$diffs = $this->compareRatings($modelRatings, $serviceRatings);

		if (empty($diffs)) {
			$output->writeln('<info>No rating differences found.</info>');
			$output->writeln(sprintf('Compared rating rows: %d', count($modelRatings)));
			return self::SUCCESS;
		}

		(new Table($output))
			->setHeaders(['Code', 'User', 'Field', 'Model', 'Service'])
			->setRows(array_slice($diffs, 0, 100))
			->render();

		$output->writeln(sprintf('<error>Found %d difference(s).</error>', count($diffs)));
		if (count($diffs) > 100) {
			$output->writeln('<comment>Only first 100 differences are shown.</comment>');
		}

		return self::FAILURE;
	}

	/**
	 * @param array<int, MinimalGameRow|object{code:string}> $rows
	 * @param string[]                                      $codes
	 * @return array<string, array<string, mixed>>
	 */
	private function captureModelRatings(array $rows, array $codes): array {
		DB::begin();
		try {
			$this->deleteRatings($codes);
			foreach ($rows as $row) {
				$game = GameFactory::getByCode($row->code);
				if (!isset($game)) {
					continue;
				}
				$this->rankCalculator->recalculateRatingForGame($game);
				ModelRepository::removeInstance($game);
				unset($game);
			}
			$ratings = $this->captureRatings($codes);
		}
		catch (Throwable $e) {
			DB::rollback();
			throw $e;
		}
		DB::rollback();

		return $ratings;
	}

	/**
	 * @param string[] $codes
	 * @return array<string, array<string, mixed>>
	 */
	private function captureServiceRatings(array $codes): array {
		DB::begin();
		try {
			$this->deleteRatings($codes);
			foreach ($codes as $code) {
				$this->rankRecalculationService->recalculateGame($code, true);
			}
			$ratings = $this->captureRatings($codes);
		}
		catch (Throwable $e) {
			DB::rollback();
			throw $e;
		}
		DB::rollback();

		return $ratings;
	}

	/**
	 * @param string[] $codes
	 * @return array<string, array<string, mixed>>
	 */
	private function captureRatings(array $codes): array {
		if (empty($codes)) {
			return [];
		}

		$rows = DB::select(
			'player_game_rating',
			'[code], [id_user], [difference], [normalized_skill], [min_skill], [max_skill]'
		)
		          ->where('[code] IN %in', $codes)
		          ->orderBy('code')
		          ->orderBy('id_user')
		          ->fetchAll(cache: false);

		$ratings = [];
		foreach ($rows as $row) {
			$key = $row->code . ':' . $row->id_user;
			$ratings[$key] = [
				'code'             => (string)$row->code,
				'id_user'          => (int)$row->id_user,
				'difference'       => (float)$row->difference,
				'normalized_skill' => $row->normalized_skill === null ? null : (float)$row->normalized_skill,
				'min_skill'        => $row->min_skill === null ? null : (float)$row->min_skill,
				'max_skill'        => $row->max_skill === null ? null : (float)$row->max_skill,
			];
		}

		return $ratings;
	}

	/**
	 * @param string[] $codes
	 */
	private function deleteRatings(array $codes): void {
		if (empty($codes)) {
			return;
		}
		DB::delete('player_game_rating', ['[code] IN %in', $codes]);
	}

	/**
	 * @param array<string, array<string, mixed>> $modelRatings
	 * @param array<string, array<string, mixed>> $serviceRatings
	 * @return array<int, array{string, int|string, string, string, string}>
	 */
	private function compareRatings(array $modelRatings, array $serviceRatings): array {
		$diffs = [];
		$keys = array_values(array_unique([...array_keys($modelRatings), ...array_keys($serviceRatings)]));
		sort($keys);

		foreach ($keys as $key) {
			$model = $modelRatings[$key] ?? null;
			$service = $serviceRatings[$key] ?? null;
			$code = (string)($model['code'] ?? $service['code'] ?? explode(':', $key)[0]);
			$userId = (int)($model['id_user'] ?? $service['id_user'] ?? explode(':', $key)[1]);

			if ($model === null || $service === null) {
				$diffs[] = [
					$code,
					$userId,
					'row',
					$model === null ? 'missing' : 'present',
					$service === null ? 'missing' : 'present',
				];
				continue;
			}

			foreach (['difference', 'normalized_skill', 'min_skill', 'max_skill'] as $field) {
				if (!$this->floatEquals($model[$field], $service[$field])) {
					$diffs[] = [
						$code,
						$userId,
						$field,
						$this->formatComparable($model[$field]),
						$this->formatComparable($service[$field]),
					];
				}
			}
		}

		return $diffs;
	}

	private function floatEquals(mixed $a, mixed $b): bool {
		if ($a === null || $b === null) {
			return $a === $b;
		}
		return abs((float)$a - (float)$b) < 0.0001;
	}

	private function formatComparable(mixed $value): string {
		if ($value === null) {
			return 'null';
		}
		return sprintf('%.6f', (float)$value);
	}

	private function executeServiceBenchmark(InputInterface $input, OutputInterface $output): int {
		$startedAt = microtime(true);
		$peakBefore = memory_get_peak_usage(true);
		$gamesProcessed = 0;
		$ratingDeltasWritten = 0;
		$affectedUserIds = [];

		$output->writeln('Benchmarking SQL DTO rank recalculation service. Changes are rolled back.');

		DB::begin();
		try {
			$games = $input->getOption('game');
			if (is_array($games) && !empty($games)) {
				foreach ($games as $code) {
					$summary = $this->rankRecalculationService->recalculateGame((string)$code, true);
					$gamesProcessed += $summary->gamesProcessed;
					$ratingDeltasWritten += $summary->ratingDeltasWritten;
					foreach ($summary->affectedUserIds as $userId) {
						$affectedUserIds[$userId] = $userId;
					}
				}
			}
			else {
				$summary = $this->rankRecalculationService->recalculateFrom($this->createSelection($input));
				$gamesProcessed = $summary->gamesProcessed;
				$ratingDeltasWritten = $summary->ratingDeltasWritten;
				$affectedUserIds = array_combine($summary->affectedUserIds, $summary->affectedUserIds) ?: [];
			}
		}
		catch (Throwable $e) {
			DB::rollback();
			throw $e;
		}
		DB::rollback();

		$totalDuration = microtime(true) - $startedAt;
		$averageDuration = $gamesProcessed > 0 ? $totalDuration / $gamesProcessed : 0.0;
		$peakMemory = memory_get_peak_usage(true) - $peakBefore;

		$output->writeln(
			[
				sprintf('Games processed: %d', $gamesProcessed),
				sprintf('Rating deltas written: %d', $ratingDeltasWritten),
				sprintf('Affected users: %d', count($affectedUserIds)),
				sprintf('Total time: %.3f s', $totalDuration),
				sprintf('Average time/game: %.3f s', $averageDuration),
				sprintf('Games/minute estimate: %.1f', $averageDuration > 0.0 ? 60 / $averageDuration : 0),
				sprintf('Additional peak memory: %.2f MiB', $peakMemory / 1024 / 1024),
			]
		);

		return self::SUCCESS;
	}

	private function createSelection(InputInterface $input): GameSelection {
		$from = $input->getOption('from');
		$to = $input->getOption('to');
		$arenaId = $input->getOption('arena');

		return new GameSelection(
			!empty($from) ? new DateTimeImmutable((string)$from) : null,
			!empty($to) ? new DateTimeImmutable((string)$to) : null,
			!empty($arenaId) ? (int)$arenaId : null,
			(int)$input->getArgument('offset'),
			(int)$input->getArgument('limit'),
		);
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
