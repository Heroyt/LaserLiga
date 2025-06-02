<?php
declare(strict_types=1);

namespace App\Cli\Commands\Inflection;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InflectionSetCommand extends Command
{

	private const string FILE = ROOT.'include/data/';
	private const array CASES = [
		1 => 'nominative',
		2 => 'genitive',
		3 => 'dative',
		4 => 'accusative',
		5 => 'vocative',
		6 => 'locative',
		7 => 'instrumental',
	];

	public static function getDefaultName(): string {
		return 'inflection:inflection-set';
	}

	public static function getDefaultDescription(): string {
		return 'Set inflection of a word';
	}

	protected function configure() : void {
		$this->addOption('male', 'm', InputOption::VALUE_NONE, 'Mark word as Male');
		$this->addOption('female', 'f', InputOption::VALUE_NONE, 'Mark word as Female');
		$this->addOption('neuter', 'o', InputOption::VALUE_NONE, 'Mark word as Neuter (default)');

		$this->addArgument('word', InputOption::VALUE_REQUIRED, 'The word to set inflection for');

		$this->addOption('nominative', 'N', InputOption::VALUE_REQUIRED, 'Nominative version of the word.');
		$this->addOption('genitive', 'G', InputOption::VALUE_REQUIRED, 'Genitive version of the word.');
		$this->addOption('dative', 'D', InputOption::VALUE_REQUIRED, 'Dative version of the word.');
		$this->addOption('accusative', 'A', InputOption::VALUE_REQUIRED, 'Accusative version of the word.');
		$this->addOption('vocative', 'V', InputOption::VALUE_REQUIRED, 'Vocative version of the word.');
		$this->addOption('locative', 'L', InputOption::VALUE_REQUIRED, 'Locative version of the word.');
		$this->addOption('instrumental', 'I', InputOption::VALUE_REQUIRED, 'Instrumental version of the word.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$gender = 'o'; // Default to neuter
		if ($input->getOption('male')) {
			$gender = 'm';
		}
		elseif ($input->getOption('female')) {
			$gender = 'w';
		}

		$word = $input->getArgument('word');

		if (empty($word)) {
			$output->writeln('<error>Word cannot be empty</error>');
			return self::FAILURE;
		}

		$nominative = $input->getOption('nominative');
		if (!empty($nominative)) {
			$this->updateInflection($word, $gender, 1, $nominative, $output);
			$output->writeln('<info>Set nominative: ' . $nominative . '</info>');
		}
		$genitive = $input->getOption('genitive');
		if (!empty($genitive)) {
			$this->updateInflection($word, $gender, 2, $genitive, $output);
			$output->writeln('<info>Set genitive: ' . $genitive . '</info>');
		}
		$dative = $input->getOption('dative');
		if (!empty($dative)) {
			$this->updateInflection($word, $gender, 3, $dative, $output);
			$output->writeln('<info>Set dative: ' . $dative . '</info>');
		}
		$accusative = $input->getOption('accusative');
		if (!empty($accusative)) {
			$this->updateInflection($word, $gender, 4, $accusative, $output);
			$output->writeln('<info>Set accusative: ' . $accusative . '</info>');
		}
		$vocative = $input->getOption('vocative');
		if (!empty($vocative)) {
			$this->updateInflection($word, $gender, 5, $vocative, $output);
			$output->writeln('<info>Set vocative: ' . $vocative . '</info>');
		}
		$locative = $input->getOption('locative');
		if (!empty($locative)) {
			$this->updateInflection($word, $gender, 6, $locative, $output);
			$output->writeln('<info>Set locative: ' . $locative . '</info>');
		}
		$instrumental = $input->getOption('instrumental');
		if (!empty($instrumental)) {
			$this->updateInflection($word, $gender, 7, $instrumental, $output);
			$output->writeln('<info>Set instrumental: ' . $instrumental . '</info>');
		}

		return self::SUCCESS;
	}

	private function updateInflection(string $word, string $gender, int $case, string $inflected, OutputInterface $output) : void {
		$file = $this::FILE . $gender . '_' . $this::CASES[$case] . '_suffixes.txt';
		$content = file_get_contents($file);
		if (!is_string($content)) {
			$output->writeln('<error>Cannot read file</error>');
			return;
		}
		$suffixes = unserialize($content, ['allowed_classes' => false]);
		if (!is_array($suffixes)) {
			$output->writeln('<error>Cannot unserialize file</error>');
			return;
		}
		$suffixes[$word] = $inflected;
		if (!file_put_contents($file, serialize($suffixes))) {
			$output->writeln('<error>Cannot write file</error>');
		}
	}

}