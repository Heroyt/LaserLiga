<?php
declare(strict_types=1);

namespace App\Cli\Commands\League;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RecalcPointsCommand extends Command
{
	use LeagueCommand;

	public static function getDefaultName(): ?string {
		return 'league:recalc-points';
	}

	public static function getDefaultDescription(): ?string {
		return 'Recalculate team points for a league';
	}

	protected function configure(): void {
		$this->addArgument(
			'league',
			InputArgument::REQUIRED,
			'League ID or slug'
		);
	}


	protected function execute(InputInterface $input, OutputInterface $output): int {
		$leagueId = $input->getArgument('league');
		$league = $this->getLeague($leagueId);
		if ($league === null) {
			$output->writeln('<error>League not found</error>');
			return Command::FAILURE;
		}

		$league->countPoints();

		return Command::SUCCESS;
	}
}