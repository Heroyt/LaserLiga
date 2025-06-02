<?php
declare(strict_types=1);

namespace App\Cli\Commands\Inflection;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenderSetCommand extends Command
{

	private const string FILE = ROOT.'include/data/man_vs_woman_suffixes.txt';

	public static function getDefaultName(): string {
		return 'inflection:gender-set';
	}

	public static function getDefaultDescription(): string {
		return 'Set a gendered word';
	}

	protected function configure() : void {
		$this->addOption('male', 'm', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, 'Male words');
		$this->addOption('female', 'f', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, 'Female words');
		$this->addOption('neuter', 'o', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, 'Neuter words');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$contents = file_get_contents($this::FILE);
		if (!is_string($contents)) {
			$output->writeln('<error>Cannot read file</error>');
			return self::FAILURE;
		}
		$mw = unserialize($contents, ['allowed_classes' => false]);
		if (!is_array($mw)) {
			$output->writeln('<error>Cannot unserialize file</error>');
			return self::FAILURE;
		}

		$maleWords = $input->getOption('male');
		assert(is_array($maleWords));
		foreach ($maleWords as $maleWord) {
			$mw[$maleWord] = 'm';
		}
		$output->writeln('<info>Inserted ' . count($maleWords) . ' male words</info>');

		$femaleWords = $input->getOption('female');
		assert(is_array($femaleWords));
		foreach ($femaleWords as $femaleWord) {
			$mw[$femaleWord] = 'w';
		}
		$output->writeln('<info>Inserted ' . count($femaleWords) . ' female words</info>');

		$neuterWords = $input->getOption('neuter');
		assert(is_array($neuterWords));
		foreach ($neuterWords as $neuterWord) {
			$mw[$neuterWord] = 'o';
		}
		$output->writeln('<info>Inserted ' . count($neuterWords) . ' neuter words</info>');

		if (!file_put_contents($this::FILE, serialize($mw))) {
			$output->writeln('<error>Cannot write file</error>');
			return self::FAILURE;
		}

		$output->writeln('<info>File written successfully</info>');

		return self::SUCCESS;
	}

}