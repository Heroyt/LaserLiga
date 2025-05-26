<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\CommandResponses\Dropbox\FileMetadata;
use App\CQRS\CommandResponses\SyncArenaImagesResponse;
use App\CQRS\Commands\Dropbox\DeleteDropboxFileCommand;
use App\CQRS\Commands\Dropbox\DownloadDropboxFileCommand;
use App\CQRS\Commands\Dropbox\ListDropboxFilesCommand;
use App\CQRS\Commands\ProcessPhotoCommand;
use App\CQRS\Commands\SyncArenaImagesCommand;
use App\Models\Photos\Photo;
use App\Services\Dropbox\TokenProvider;
use App\Services\ImageService;
use Lsr\CQRS\CommandBus;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Logger;
use Spatie\Dropbox\Client;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Throwable;

final readonly class SyncArenaImagesCommandHandler implements CommandHandlerInterface
{
	public function __construct(
		private CommandBus   $commandBus,
		private ImageService $imageService,
		private LockFactory  $lockFactory,
	) {
	}


	/**
	 * @param SyncArenaImagesCommand $command
	 */
	public function handle(CommandInterface $command): SyncArenaImagesResponse {
		$logger = new Logger(LOG_DIR, 'photo-sync-' . Strings::webalize($command->arena->name));
		$lock = $this->lockFactory->createLock('photo-sync-' . $command->arena->id);

		if (!$lock->acquire()) {
			$logger->warning('failed to acquire lock');
			return new SyncArenaImagesResponse();
		}

		$response = new SyncArenaImagesResponse();
		// Check arena settings
		$arena = $command->arena;
		if ($arena->dropbox->apiKey === null) {
			$logger->error('Dropbox API key is not set');
			$response->errors[] = 'Dropbox API key is not set';
			return $response;
		}

		$logger->info('Performing photo sync');

		/** @var non-falsy-string $baseIdentifier Base file identifier to be used when uploading to S3 */
		$baseIdentifier = 'photos/' . Strings::webalize($arena->name) . '/';

		// Initialize dropbox client
		$client = new Client(new TokenProvider($arena));

		/** @var OutputInterface|null $output */
		$output = $command->output;

		// Get photos from Dropbox
		$command->output?->writeln('Listing all dropbox files');
		$logger->debug('Listing directory ' . ($arena->dropbox->directory ?? '/'));
		$entries = $this->commandBus->dispatch(
			new ListDropboxFilesCommand(
				$client,
				$arena->dropbox->directory ?? '/',
				true,
				['jpg', 'jpeg', 'png', 'heic', 'heif']
			)
		);

		/** @var FileMetadata $entry */
		foreach ($entries as $entry) {
			if ($command->output instanceof ConsoleOutputInterface) {
				$output = $command->output->section();
			}
			$output?->writeln('Processing file ' . $entry->pathDisplay);
			$logger->debug('Processing file ' . $entry->pathDisplay);
			$tempFileBase = TMP_DIR . uniqid('dropbox_img_', true);
			$tempFile = $tempFileBase . '.' . $entry->fileType;

			try {
				if (!$this->commandBus->dispatch(
					new DownloadDropboxFileCommand($client, $entry->pathDisplay, $tempFile)
				)) {
					$response->errors[] = 'Failed to download file ' . $entry->pathDisplay;
					continue;
				}
				$logger->debug('File downloaded ' . $tempFile);
				$output?->writeln('File downloaded ' . $tempFile);

				// Process downloaded photo
				// - Convert heic to jpg
				// - Create photo entity
				// - Create resized variants
				// - Upload to S3
				// - Will not delete the local file
				$photo = $this->commandBus->dispatch(
					new ProcessPhotoCommand(
						arena         : $command->arena,
						filename      : $tempFile,
						fileType      : $entry->fileType,
						filePublicName: $entry->pathDisplay,
						optimizeSizes : $command->optimizeSizes,
						output        : $output,
						logger        : $logger,
					)
				);

				// Delete the original file - either processed successfully, or it will be downloaded again.
				if (file_exists($tempFile)) {
					unlink($tempFile);
				}

				// If photo is a string, it means that the processing failed, and the string contains an error message..
				if (is_string($photo)) {
					$response->errors[] = 'Failed to process file ' . $entry->pathDisplay . ' - ' . $photo;
					continue;
				}

				// Delete the file from Dropbox after successful processing
				$logger->debug('deleting dropbox file ' . $entry->pathDisplay);
				$deleteResponse = $this->commandBus->dispatch(
					new DeleteDropboxFileCommand($client, $entry->pathDisplay)
				);
				if (!$deleteResponse->isOk()) {
					$logger->error('Failed to delete dropbox file ' . $entry->pathDisplay);
					$response->errors[] = 'Failed to delete dropbox file ' . $entry->pathDisplay;
					continue;
				}
				$response->count++;
				$response->photos[] = $photo;
			} catch (Throwable $e) {
				$logger->exception($e);
				if (file_exists($tempFile)) {
					unlink($tempFile);
				}
				$lock->release();
				throw $e;
			}

			// Limit the amount processed files.
			if ($command->limit !== null && $response->count >= $command->limit) {
				break;
			}
		}

		Photo::clearQueryCache();
		$lock->release();
		return $response;
	}
}