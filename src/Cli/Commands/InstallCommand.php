<?php
declare(strict_types=1);

namespace App\Cli\Commands;

use App\Install\Install;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends Command
{

	public static function getDefaultName(): ?string {
		return 'install';
	}

	public static function getDefaultDescription(): ?string {
		return 'Install the database';
	}

	protected function configure(): void {
		$this->addOption(
			'fresh',
			'f',
			InputOption::VALUE_NONE,
			'Fresh install',
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$fresh = $input->getOption('fresh');
		if (Install::install($fresh, $output)) {
			return self::SUCCESS;
		}
		return self::FAILURE;
	}

}