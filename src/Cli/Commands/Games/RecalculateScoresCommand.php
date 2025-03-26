<?php
declare(strict_types=1);

namespace App\Cli\Commands\Games;

use App\GameModels\Factory\GameFactory;
use Lsr\CQRS\CommandBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RecalculateScoresCommand extends Command
{

	public function __construct(
		private readonly CommandBus $commandBus,
	) {
		parent::__construct();
	}

	public static function getDefaultName(): string {
		return 'games:recalculate-scores';
	}

	public static function getDefaultDescription(): string {
		return 'Recalculate scores for games.';
	}

	protected function configure() : void {
		$this->addArgument('games', InputArgument::IS_ARRAY|InputArgument::REQUIRED, 'Games codes');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$games = $input->getArgument('games');
		if (!is_array($games)) {
			$output->writeln('<error>Games codes must be an array.</error>');
			return self::FAILURE;
		}

		foreach ($games as $gameCode) {
			$game = GameFactory::getByCode($gameCode);
			if (!isset($game)) {
				$output->writeln('<error>Game not found: ' . $gameCode . '</error>');
				continue;
			}
			$output->writeln('Recalculating scores for game ' . $gameCode);
			$command = new \App\CQRS\Commands\RecalculateScoresCommand($game);
			if (!$this->commandBus->dispatch($command)) {
				$output->writeln('<error>Failed to recalculate scores for game ' . $gameCode . '</error>');
			}
		}

		return self::SUCCESS;
	}

}