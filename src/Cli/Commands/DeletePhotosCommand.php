<?php
declare(strict_types=1);

namespace App\Cli\Commands;

use App\Models\Photos\Photo;
use Lsr\CQRS\CommandBus;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeletePhotosCommand extends Command
{

	public function __construct(
		private readonly CommandBus $commandBus,
	) {
		parent::__construct();
	}

	public static function getDefaultName(): ?string {
		return 'delete:arena-images';
	}

	public static function getDefaultDescription(): ?string {
		return 'Remove photos from an s3 bucket and DB.';
	}

	protected function configure() : void {
		$this->addArgument(
			'ids',
			InputArgument::REQUIRED|InputArgument::IS_ARRAY,
			'Photo IDs'
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$ids = $input->getArgument('ids');
		if (count($ids) === 0) {
			$output->writeln('<error>No photo IDs provided</error>');
			return self::FAILURE;
		}

		$photos = [];
		foreach ($ids as $id) {
			try {
				$photo = Photo::get((int) $id);
			} catch (ModelNotFoundException $e) {
				$output->writeln("<error>Photo $id does not exist</error>");
				return self::FAILURE;
			}
			$photos[] = $photo;
		}
		if (count($photos) === 0) {
			$output->writeln('<error>No photos found</error>');
			return self::FAILURE;
		}

		$command = new \App\CQRS\Commands\DeletePhotosCommand($photos, output: $output);
		$response = $this->commandBus->dispatch($command);

		$output->writeln('<info>Removed '.$response->count.' photos</info>');

		if (!empty($response->errors)) {
			$output->writeln('<error>Errors:</error>');
			foreach ($response->errors as $error) {
				$output->writeln("<error>$error</error>");
			}
		}

		return self::SUCCESS;
	}


}