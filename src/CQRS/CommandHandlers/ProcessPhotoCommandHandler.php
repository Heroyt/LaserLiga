<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\CommandResponses\S3\PutObjectResponse;
use App\CQRS\Commands\ProcessPhotoCommand;
use App\CQRS\Commands\S3\UploadFileToS3Command;
use App\Exceptions\FileException;
use App\Models\Photos\Photo;
use App\Models\Photos\PhotoVariation;
use App\Services\ImageService;
use DateTimeImmutable;
use Lsr\CQRS\CommandBus;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Logger;
use Maestroerror\HeicToJpg;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final readonly class ProcessPhotoCommandHandler implements CommandHandlerInterface
{

	public function __construct(
		private CommandBus   $commandBus,
		private ImageService $imageService,
	) {
	}

	/**
	 * @param ProcessPhotoCommand $command
	 */
	public function handle(CommandInterface $command): string|Photo {
		$logger = $command->logger ?? new Logger(LOG_DIR, 'photo-sync-' . Strings::webalize($command->arena->name));
		/** @var OutputInterface|null $output */
		$output = $command->output;

		$file = $command->filename;
		$fileType = $command->fileType ?? strtolower(pathinfo($file, PATHINFO_EXTENSION));
		$fileBase = substr($command->filename, 0, -(strlen(pathinfo($file, PATHINFO_EXTENSION)) + 1));
		$publicName = $command->filePublicName ?? $command->filename;

		/** @var non-falsy-string $baseIdentifier Base file identifier to be used when uploading to S3 */
		$baseIdentifier = 'photos/' . Strings::webalize($command->arena->name) . '/';

		$images = [];
		$commited = true;
		try {
			// Convert heic to jpg
			if (($fileType === 'heic' || $fileType === 'heif') && HeicToJpg::isHeic($file)) {
				$logger->debug('Converting file from HEIC ' . $file);
				$output?->writeln('Converting file from HEIC ' . $file);
				$convertedFile = $fileBase . '.jpg';
				if (!HeicToJpg::convert($file, $convertedFile)->saveAs($convertedFile)) {
					$logger->error('Failed to convert file from HEIC ' . $file);
					return 'Failed to convert file from HEIC ' . $file;
				}
				unlink($file); // Remove the heic file
				$file = $convertedFile;
				$fileType = 'jpg';
			}

			// Read exif info
			$exif = exif_read_data($command->filename);
			if ($exif !== false && isset($exif['DateTime'])) {
				$exifTime = new DateTimeImmutable($exif['DateTime']);
				$identifier = $baseIdentifier . strtotime($exif['DateTime']);
			}
			else {
				$exifTime = new DateTimeImmutable();
				$identifier = $baseIdentifier . uniqid('', true);
			}

			// Find or create entity
			$photo = Photo::findOrCreateByIdentifier($identifier . '.' . $fileType, false);
			$photo->arena = $command->arena;
			$photo->exifTime = $exifTime;

			// Upload to S3
			/** @var PutObjectResponse $putResponse */
			$putResponse = $this->commandBus->dispatch(
				new UploadFileToS3Command(
					filename  : $file,
					identifier: $photo->identifier,
					bucket    : $command->arena->photosSettings->bucket,
				)
			);
			$photo->url = $putResponse->ObjectURL;

			DB::begin();
			$commited = false;
			if (!$photo->save()) {
				$logger->error('Failed to save photo ' . $publicName);
				DB::rollback();
				return 'Failed to save photo ' . $publicName;
			}
			$logger->debug('File uploaded to S3 ' . $photo->identifier);
			$output?->writeln('File uploaded to S3 ' . $photo->identifier);

			// Optimize image
			try {
				$images = $this->imageService->optimize($file, $command->optimizeSizes);
			} catch (FileException $e) {
				$logger->error('Failed to optimize image ' . $publicName . ' - ' . $e->getMessage());
				DB::rollback();
				return 'Failed to optimize image ' . $publicName . ' - ' . $e->getMessage();
			}

			$logger->debug('Optimized images:' . count($images));
			$output?->writeln('Optimized images:' . count($images));

			// Process optimized images
			foreach ($images as $type => $image) {
				$ext = pathinfo($image, PATHINFO_EXTENSION);
				$variation = PhotoVariation::findOrCreateByIdentifier($identifier . '-' . $type . '.' . $ext);
				$variation->photo = $photo;
				$variation->type = $ext;
				$size = getimagesize($image);
				if ($size === false) {
					$logger->error('Failed to get image size for optimized image ' . $image . ' (' . $publicName . ')');
					DB::rollback();
					return 'Failed to get image size for optimized image ' . $image . ' (' . $publicName . ')';
				}
				$variation->size = $size[0];

				// Upload to S3
				/** @var PutObjectResponse $putResponse */
				$putResponse = $this->commandBus->dispatch(
					new UploadFileToS3Command(
						filename  : $image,
						identifier: $variation->identifier,
						bucket    : $command->arena->photosSettings->bucket,
					)
				);
				$variation->url = $putResponse->ObjectURL;
				if (!$variation->save()) {
					$logger->error(
						'Failed to save photo variation ' . $image . ' (' . $publicName . ')'
					);
					DB::rollback();
					return 'Failed to save photo variation ' . $image . ' (' . $publicName . ')';
				}
				$output?->writeln('Uploaded image variation ' . $variation->identifier);
				$photo->variations->add($variation);
			}

			// Commit DB changes
			DB::commit();
			$commited = true;

			// Now, that everything is saved in the DB and uploaded to S3
			// delete temporary and dropbox files.
			foreach ($images as $image) {
				unlink($image);
			}
		} catch (Throwable $e) {
			// Clean-up on error
			if (!$commited) {
				DB::rollback();
			}
			foreach ($images as $image) {
				if (file_exists($image)) {
					unlink($image);
				}
			}
			throw $e;
		}

		return $photo;
	}
}