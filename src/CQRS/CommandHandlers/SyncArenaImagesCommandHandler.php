<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\CommandResponses\Dropbox\FileMetadata;
use App\CQRS\CommandResponses\S3\PutObjectResponse;
use App\CQRS\CommandResponses\SyncArenaImagesResponse;
use App\CQRS\Commands\Dropbox\DeleteDropboxFileCommand;
use App\CQRS\Commands\Dropbox\DownloadDropboxFileCommand;
use App\CQRS\Commands\Dropbox\ListDropboxFilesCommand;
use App\CQRS\Commands\S3\UploadFileToS3Command;
use App\CQRS\Commands\SyncArenaImagesCommand;
use App\Exceptions\FileException;
use App\Models\Photos\Photo;
use App\Models\Photos\PhotoVariation;
use App\Services\Dropbox\TokenProvider;
use App\Services\ImageService;
use DateTimeImmutable;
use Lsr\CQRS\CommandBus;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Strings;
use Spatie\Dropbox\Client;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class SyncArenaImagesCommandHandler implements CommandHandlerInterface
{
	public function __construct(
		private CommandBus   $commandBus,
		private ImageService $imageService,
	) {
	}


	/**
	 * @param SyncArenaImagesCommand $command
	 */
	public function handle(CommandInterface $command): SyncArenaImagesResponse {
		$response = new SyncArenaImagesResponse();
		// Check arena settings
		$arena = $command->arena;
		if ($arena->dropbox->apiKey === null) {
			$response->errors[] = 'Dropbox API key is not set';
			return $response;
		}

		/** @var non-falsy-string $baseIdentifier Base file identifier to be used when uploading to S3 */
		$baseIdentifier = 'photos/' . Strings::webalize($arena->name) . '/';

		// Initialize dropbox client
		$client = new Client(new TokenProvider($arena));

		/** @var OutputInterface|null $output */
		$output = $command->output;

		// Get photos from Dropbox
		$command->output?->writeln('Listing all dropbox files');
		$entries = $this->commandBus->dispatch(
			new ListDropboxFilesCommand(
				$client,
				$arena->dropboxDirectory ?? '/',
				true,
				['jpg', 'jpeg', 'png']
			)
		);

		/** @var FileMetadata $entry */
		foreach ($entries as $entry) {
			if ($command->output instanceof ConsoleOutputInterface) {
				$output = $command->output->section();
			}
			$output?->writeln('Processing file ' . $entry->pathDisplay);
			$tempFileBase = TMP_DIR . uniqid('dropbox_img_', true);
			$tempFile = $tempFileBase . '.' . $entry->fileType;

			// Start transaction
			DB::begin();

			try {
				if (!$this->commandBus->dispatch(
					new DownloadDropboxFileCommand($client, $entry->pathDisplay, $tempFile)
				)) {
					$response->errors[] = 'Failed to download file ' . $entry->pathDisplay;
					continue;
				}
				$output?->writeln('File downloaded ' . $tempFile);

				// Read exif info
				$exif = exif_read_data($tempFile);
				$exifTime = null;
				if ($exif !== false && isset($exif['DateTime'])) {
					$exifTime = new DateTimeImmutable($exif['DateTime']);
					$identifier = $baseIdentifier . strtotime($exif['DateTime']);
				}
				else {
					$exifTime = new DateTimeImmutable();
					$identifier = $baseIdentifier . uniqid('', true);
				}

				// Find or create entity
				$photo = Photo::findOrCreateByIdentifier($identifier . '.' . $entry->fileType);
				$photo->arena = $command->arena;
				$photo->exifTime = $exifTime;

				// Upload to S3
				/** @var PutObjectResponse $putResponse */
				$putResponse = $this->commandBus->dispatch(new UploadFileToS3Command($tempFile, $photo->identifier));
				$photo->url = $putResponse->ObjectURL;
				if (!$photo->save()) {
					$response->errors[] = 'Failed to save photo ' . $entry->pathDisplay;
					DB::rollback();
					continue;
				}
				$output?->writeln('File uploaded to S3 ' . $photo->identifier);

				// Optimize image
				try {
					$images = $this->imageService->optimize($tempFile, $command->optimizeSizes);
				} catch (FileException $e) {
					$response->errors[] = 'Failed to optimize image ' . $entry->pathDisplay . ' - ' . $e->getMessage();
					DB::rollback();
					continue;
				}

				$output?->writeln('Optimized images:'.count($images));

				// Process optimized images
				foreach ($images as $type => $image) {
					$ext = pathinfo($image, PATHINFO_EXTENSION);
					$variation = PhotoVariation::findOrCreateByIdentifier($identifier . '-' . $type . '.' . $ext);
					$variation->photo = $photo;
					$variation->type = $ext;
					$size = getimagesize($image);
					if ($size === false) {
						$response->errors[] = 'Failed to get image size for optimized image ' . $image . ' (' . $entry->pathDisplay . ')';
						DB::rollback();
						continue 2;
					}
					$variation->size = $size[0];

					// Upload to S3
					/** @var PutObjectResponse $putResponse */
					$putResponse = $this->commandBus->dispatch(
						new UploadFileToS3Command($image, $variation->identifier)
					);
					$variation->url = $putResponse->ObjectURL;
					if (!$variation->save()) {
						$response->errors[] = 'Failed to save photo variation ' . $image . ' (' . $entry->pathDisplay . ')';
						DB::rollback();
						continue 2;
					}
					$output?->writeln('Uploaded image variation ' . $variation->identifier);
					$photo->variations->add($variation);
				}

				// Now, that everything is saved in the DB and uploaded to S3
				// delete temporary and dropbox files.
				unlink($tempFile);
				foreach ($images as $image) {
					unlink($image);
				}
				$deleteResponse = $this->commandBus->dispatch(
					new DeleteDropboxFileCommand($client, $entry->pathDisplay)
				);
				if (!$deleteResponse->isOk()) {
					$response->errors[] = 'Failed to delete dropbox file ' . $entry->pathDisplay;
					DB::rollback();
					continue;
				}

				// Commit DB changes
				DB::commit();
				$response->count++;
				$response->photos[] = $photo;
			} catch (\Throwable $e) {
				// Cleanup if something went wrong
				DB::rollback();
				if (file_exists($tempFile)) {
					unlink($tempFile);
				}
				throw $e;
			}

			// Limit the amount processed files.
			if ($command->limit !== null && $response->count >= $command->limit) {
				break;
			}
		}

		return $response;
	}
}