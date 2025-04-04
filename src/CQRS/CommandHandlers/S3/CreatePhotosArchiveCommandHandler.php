<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers\S3;

use App\CQRS\Commands\S3\CreatePhotosArchiveCommand;
use App\CQRS\Commands\S3\UploadFileToS3Command;
use App\Models\Photos\PhotoArchive;
use DateTimeImmutable;
use GrahamCampbell\GuzzleFactory\GuzzleFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Lsr\CQRS\CommandBus;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Logger;
use ZipArchive;

final readonly class CreatePhotosArchiveCommandHandler implements CommandHandlerInterface
{

	public function __construct(
		private CommandBus $commandBus,
	){}

	/**
	 * @param CreatePhotosArchiveCommand $command
	 */
	public function handle(CommandInterface $command): ?PhotoArchive {
		$logger = new Logger(LOG_DIR, 'photos_archive');
		$client = new Client(['handler' => GuzzleFactory::handler()]);

		// Find first photo with a game
		$game = null;
		foreach ($command->photos as $photo) {
			if ($photo->game !== null) {
				$game = $photo->game;
				break;
			}
		}

		$downloadDir = TMP_DIR . '/download/';
		if (!is_dir($downloadDir) && !mkdir($downloadDir, 0777, true)) {
			$logger->error('Failed to create download directory');
			return null;
		}

		if ($game !== null) {
			$logger->info('Creating archive for game: '.$game->code);
			$archive = PhotoArchive::getForGame($game);
			if ($archive === null) {
				$archive = new PhotoArchive();
				$archive->arena = $command->arena;
				$archive->game = $game;
				$archive->identifier = 'archives/' . Strings::webalize(
						$command->arena->name
					) . '/' . $game->code . '.zip';
				$archive->arena = $photo->arena;
				$archive->createdAt = new DateTimeImmutable();
			}
		}
		else {
			$logger->info('Creating archive without a game');
			$archive = new PhotoArchive();
			$archive->arena = $command->arena;
			$archive->identifier = 'archives/' . Strings::webalize($command->arena->name) . '/' . uniqid(
					'archive_'
				) . '.zip';
			$archive->createdAt = new DateTimeImmutable();
		}

		// Prepare local zip file
		$tmpZip = $downloadDir . uniqid('archive_') . '.zip';
		$zip = new ZipArchive();
		if ($zip->open($tmpZip, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
			$logger->error('Failed to open zip file');
			return null;
		}

		// Download photos into a zip file
		$tempFiles = [$tmpZip];
		foreach ($command->photos as $photo) {
			$fName = basename($photo->url);
			$tempFile = $downloadDir . $fName;
			try {
				$client->get($photo->url, ['sink' => $tempFile]);
			} catch (GuzzleException $e) {
				$this->cleanup($tempFiles);
				$logger->exception($e);
				return null;
			}
			$zip->addFile($tempFile, $fName);
			$zip->setCompressionName($fName, ZipArchive::CM_DEFAULT);
			$tempFiles[] = $fName;
		}
		if (!$zip->close()) {
			$logger->error('Failed to close zip file');
			$this->cleanup($tempFiles);
			return null;
		}

		// Upload zip file to S3
		$response = $this->commandBus->dispatch(new UploadFileToS3Command($tmpZip, $archive->identifier));

		// Update archive with S3 URL
		$archive->url = $response->ObjectURL;

		// Save
		if (!$archive->save()) {
			$logger->error('Failed saving photo archive');
			$this->cleanup($tempFiles);
			return null;
		}
		$archive->clearCache();
		$archive::clearQueryCache();

		$this->cleanup($tempFiles);
		return $archive;
	}

	/**
	 * @param string[] $tempFiles
	 */
	private function cleanup(array $tempFiles): void {
		foreach ($tempFiles as $file) {
			if (file_exists($file)) {
				unlink($file);
			}
		}
	}
}