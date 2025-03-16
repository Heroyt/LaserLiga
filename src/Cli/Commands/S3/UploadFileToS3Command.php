<?php
declare(strict_types=1);

namespace App\Cli\Commands\S3;

use Lsr\CQRS\CommandBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Serializer;

class UploadFileToS3Command extends Command
{

	public function __construct(
		private readonly CommandBus $commandBus,
		private readonly Serializer $serializer,
	) {
		parent::__construct('s3:upload-file');
	}

	public static function getDefaultName(): string {
		return 's3:upload-file';
	}

	public static function getDefaultDescription(): string {
		return 'Upload file to S3';
	}

	protected function configure() : void {
		$this->addArgument('file', InputArgument::REQUIRED, 'File to upload');
		$this->addArgument('identifier', InputArgument::OPTIONAL, 'Identifier');
	}

	protected function execute(InputInterface $input, OutputInterface $output) : int {
		$filename = $input->getArgument('file');
		$identifier = $input->getArgument('identifier');

		$result = $this->commandBus->dispatch(new \App\CQRS\Commands\S3\UploadFileToS3Command($filename, $identifier));

		$output->writeln($this->serializer->serialize($result, 'json'));

		return Command::SUCCESS;
	}

}