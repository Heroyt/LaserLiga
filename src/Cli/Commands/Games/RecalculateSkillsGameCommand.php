<?php

namespace App\Cli\Commands\Games;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\DataObjects\Game\MinimalGameRow;
use App\Services\Player\RankCalculator;
use Dibi\Exception;
use Lsr\Db\DB;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class RecalculateSkillsGameCommand extends Command
{

	private int $i = 0;

	public function __construct(
		private readonly RankCalculator $rankCalculator,
	) {
		parent::__construct();
	}

	public static function getDefaultName(): ?string {
		return 'games:skills';
	}

	public static function getDefaultDescription(): ?string {
		return 'Recalculate game skills.';
	}

	protected function configure(): void {
		$this->addOption('game', 'g', InputOption::VALUE_REQUIRED, 'Game code');
		$this->addOption('arena', 'a', InputOption::VALUE_REQUIRED, 'Arena ID');
		$this->addOption('rank', 'r', InputOption::VALUE_NONE, 'With rank');
		$this->addOption('user', 'u', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'User ID');
		$this->addOption('withuser', 'w', InputOption::VALUE_NONE, 'Only games with users');
		$this->addOption('rankable', 'R', InputOption::VALUE_NONE, 'Only rankable games');
		$this->addArgument('offset', InputArgument::OPTIONAL, 'Games DB offset', 0);
		$this->addArgument('limit', InputArgument::OPTIONAL, 'Games DB limit', 200);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$withRank = $input->getOption('rank');
		$rankable = $input->getOption('rankable');
		$withUser = $input->getOption('withuser');
		$game = $input->getOption('game');
		if (!empty($game)) {
			$game = GameFactory::getByCode($game);
			if ($game === null) {
				$output->writeln('<error>Game not found</error>');
				return self::FAILURE;
			}
			$this->calculateGame($output, $game, $withRank);
			return self::SUCCESS;
		}

		$limit = (int)$input->getArgument('limit');
		$offset = (int)$input->getArgument('offset');

		$this->i = $offset;

		$users = $input->getOption('user');
		if (!empty($users)) {
			$query = PlayerFactory::queryPlayersWithGames()
			                      ->where('id_user IN %in', array_map('intval', $users))
			                      ->groupBy('code');
		}
		elseif ($withUser) {
			$query = PlayerFactory::queryPlayersWithGames()
								->where('id_user IS NOT NULL')
			                      ->groupBy('code');
		}
		else {
			$query = GameFactory::queryGames(true);
		}

		$query
			->orderBy('start')
			->limit($limit)
			->offset($offset);

		if ($rankable) {
			$modes = DB::select(AbstractMode::TABLE, '[id_mode], [name]')
			           ->where('[rankable] = 1')
			           ->cacheTags(AbstractMode::TABLE, 'modes/rankable')
			           ->fetchPairs('id_mode', 'name');
			$query->where('id_mode IN %in', array_keys($modes));
		}

		$arenaId = $input->getOption('arena');
		if (!empty($arenaId)) {
			$query->where('id_arena = %i', $arenaId);
		}

		$games = $query->fetchIteratorDto(MinimalGameRow::class, false);

		foreach ($games as $row) {
			$game = GameFactory::getByCode($row->code);
			if (!isset($game)) {
				continue;
			}
			$this->calculateGame($output, $game, $withRank);
			unset($game);
		}

		$output->writeln(
			Colors::color(ForegroundColors::GREEN) . 'Done' . Colors::reset()
		);
		return self::SUCCESS;
	}

	/**
	 * @param OutputInterface $output
	 * @param Game            $game
	 * @param mixed           $withRank
	 *
	 * @return void
	 * @throws GameModeNotFoundException
	 * @throws Exception
	 * @throws ModelNotFoundException
	 * @throws Throwable
	 */
	public function calculateGame(OutputInterface $output, Game $game, mixed $withRank): void {
		$output->writeln(
			sprintf(
				str_pad('#' . $this->i, 6) . ' Calculating game %s (%s)',
				$game->start->format('d.m.Y H:i'),
				$game->code
			)
		);
		$this->i++;
		$game->calculateSkills();
		if (!$game->save()) {
			$output->writeln('<error>Failed to save game into DB</error>');
			return;
		}
		if ($withRank) {
			$this->rankCalculator->recalculateRatingForGame($game);
		}
		$game->clearCache();
	}
}
