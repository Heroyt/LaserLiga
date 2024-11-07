<?php
declare(strict_types=1);

namespace App\Cli\Commands\Games;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\GameModels\Factory\GameFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AssignGameModesCommand extends Command
{
	public static function getDefaultName(): ?string {
		return 'games:game-modes';
	}

	public static function getDefaultDescription(): ?string {
		return 'Assign game modes to empty games.';
	}

	protected function configure(): void {
		$this->addOption('arena', 'a', InputOption::VALUE_REQUIRED, 'Arena ID');
		$this->addArgument('offset', InputArgument::OPTIONAL, 'Games DB offset', 0);
		$this->addArgument('limit', InputArgument::OPTIONAL, 'Games DB limit', 200);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$arenaId = $input->getOption('arena');
		$limit = (int)$input->getArgument('limit');
		$offset = (int)$input->getArgument('offset');

		$query = GameFactory::queryGames(true)
							->where('id_mode IS NULL')
		                    ->orderBy('start')
		                    ->desc();

		if ($arenaId) {
			$query->where('id_arena = %i', (int) $arenaId);
		}

		$games = $query->getIterator($offset, $limit);

		$count = 0;
		foreach ($games as $row) {
			$game = GameFactory::getByCode($row->code);
			if (!isset($game)) {
				continue;
			}
			$game->getMode();
			$output->writeln($game->code . ' Mode: ' .$game->modeName .' '. ($game->mode->name ?? 'unknown').' '.($game->mode->id ?? 'NULL'));
			if (!$game->save()) {
				$output->writeln('<error>Failed to save game into DB</error>');
			}
			else {
				$count++;
			}
			unset($game);
		}

		$output->writeln('<info>Done - '.$count.' games</info>');
		return self::SUCCESS;
	}

}