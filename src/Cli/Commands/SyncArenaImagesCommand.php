<?php
declare(strict_types=1);

namespace App\Cli\Commands;

use App\CQRS\CommandResponses\SyncArenaImagesResponse;
use App\Models\Arena;
use Lsr\CQRS\CommandBus;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncArenaImagesCommand extends Command
{

	public function __construct(
		private readonly CommandBus $commandBus,
	) {
		parent::__construct();
	}

	public static function getDefaultName(): ?string {
		return 'sync:arena-images';
	}

	public static function getDefaultDescription(): ?string {
		return 'Sync arena images from dropbox to s3';
	}

	protected function configure() : void {
		$this->addArgument(
			'arena',
			InputArgument::REQUIRED,
			'Arena ID'
		);
		$this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit the amount of images to sync');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$arenaId = (int) $input->getArgument('arena');
		$limit = $input->getOption('limit');
		$limit = is_numeric($limit) ? (int) $limit : null;

		try {
			$arena = Arena::get($arenaId);
		} catch (ModelNotFoundException $e) {
			$output->writeln('<error>Arena does not exist</error>');
			return self::FAILURE;
		}

		if (empty($arena->dropboxApiKey)) {
			$output->writeln('<error>Dropbox API key is not set</error>');
			return self::FAILURE;
		}

		$command = new \App\CQRS\Commands\SyncArenaImagesCommand($arena, limit: $limit, output: $output);
		/** @var SyncArenaImagesResponse $response */
		$response = $this->commandBus->dispatch($command);

		$output->writeln('<info>Synced '.$response->count.' images</info>');

		if (!empty($response->errors)) {
			$output->writeln('<error>Errors:</error>');
			foreach ($response->errors as $error) {
				$output->writeln("<error>$error</error>");
			}
		}

		return self::SUCCESS;
	}


}