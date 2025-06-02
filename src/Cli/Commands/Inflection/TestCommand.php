<?php
declare(strict_types=1);

namespace App\Cli\Commands\Inflection;

use App\GameModels\Game\Evo6\Player;
use App\Services\GenderService;
use App\Services\NameInflectionService;
use Lsr\Core\App;
use Lsr\Db\DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TestCommand extends Command
{

	public static function getDefaultName(): string {
		return 'inflection:test';
	}

	public static function getDefaultDescription(): string {
		return 'Test gender ranking and inflection';
	}

	protected function configure(): void {
		$this->addOption('random', 'r', InputOption::VALUE_OPTIONAL, 'Try random names from DB', 0);
		$this->addArgument('word', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Words to test');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$style = new SymfonyStyle($input, $output);

		App::getInstance()->translations->setLang('cs');

		$random = (int)$input->getOption('random');
		if ($random > 0) {
			// Get random names from the database
			$names = DB::select(Player::TABLE, ['name'])
			           ->unionAll(
				           DB::select(\App\GameModels\Game\Evo5\Player::TABLE, ['name'])
			           )
			           ->limit($random)
			           ->orderBy('RAND()')
			           ->fetchIterator(false);
			foreach ($names as $name) {
				$this->testWord($name->name, $style);
			}
		}

		foreach ($input->getArgument('word') as $word) {
			$this->testWord($word, $style);
		}

		return self::SUCCESS;
	}

	private function testWord(string $word, SymfonyStyle $output): void {
		$output->section($word);
		$output->definitionList(
			[
				'Gender' => GenderService::rankWord($word)->getReadable(),
			],
			new TableSeparator(),
			[
				'Nominative' => NameInflectionService::nominative($word),
			],
			[
				'Genitive' => NameInflectionService::genitive($word),
			],
			[
				'Dative' => NameInflectionService::dative($word),
			],
			[
				'Accusative' => NameInflectionService::accusative($word),
			],
			[
				'Vocative' => NameInflectionService::vocative($word),
			],
			[
				'Locative' => NameInflectionService::locative($word),
			],
			[
				'Instrumental' => NameInflectionService::instrumental($word),
			],
			[
			]
		);
	}

}