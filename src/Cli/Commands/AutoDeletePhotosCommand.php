<?php
declare(strict_types=1);

namespace App\Cli\Commands;

use App\Models\Arena;
use Lsr\CQRS\CommandBus;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class AutoDeletePhotosCommand extends Command
{

	public function __construct(
		private readonly CommandBus $commandBus,
	) {
		parent::__construct();
	}

	public static function getDefaultName(): string {
		return 'delete:photos-auto';
	}

	public static function getDefaultDescription(): string {
		return 'Delete old photos and photo archives from the database and S3 bucket';
	}

	protected function configure(): void {
		$this->addArgument('arena', InputArgument::REQUIRED, 'Arena ID');
		$this->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Do not delete anything, just show what would be deleted');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$arenaId = (int) $input->getArgument('arena');
		$dryRun = (bool) $input->getOption('dry-run');
		try {
			$arena = Arena::get($arenaId);
		} catch (ModelNotFoundException $e) {
			$output->writeln('<error>Arena not found</error>');
			return self::FAILURE;
		}
		$response = $this->commandBus->dispatch(
			new \App\CQRS\Commands\AutoDeletePhotosCommand(
				$arena,
				$dryRun,
				$output
			)
		);
		return self::SUCCESS;
	}

}