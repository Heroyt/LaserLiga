<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\CommandResponses\AutoDeletePhotosResponse;
use App\CQRS\Commands\AutoDeletePhotosCommand;
use App\CQRS\Commands\DeletePhotosCommand;
use App\CQRS\Commands\S3\RemoveFilesFromS3Command;
use App\CQRS\Queries\Photos\PhotoArchivesToDeleteQuery;
use App\CQRS\Queries\Photos\PhotosToDeleteQuery;
use Lsr\CQRS\CommandBus;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;

final readonly class AutoDeletePhotosCommandHandler implements CommandHandlerInterface
{

	public function __construct(
		private CommandBus $commandBus,
	) {
	}

	/**
	 * @param AutoDeletePhotosCommand $command
	 */
	public function handle(CommandInterface $command): AutoDeletePhotosResponse {
		$response = new AutoDeletePhotosResponse();

		// Find photos to delete
		$photos = new PhotosToDeleteQuery($command->arena)->noCache()->get();
		$command->output?->writeln(sprintf('Found %d photos to delete', count($photos)));

		// Delete photos
		foreach ($photos as $photo) {
			if ($command->output?->isVerbose() ?? false) {
				$command->output->writeln(
					sprintf(
						'Deleting photo #%d %s: %s',
						$photo->id,
						$photo->identifier,
						$photo->createdAt->format('Y-m-d H:i:s')
					)
				);
			}
			if ($command->dryRun) {
				$response->photosDebug[] = $photo;
				continue;
			}
			$deleteResponse = $this->commandBus->dispatch(new DeletePhotosCommand([$photo], $command->output));
			if ($command->output?->isVerbose() ?? false) {
				$command->output->writeln(
					sprintf(
						'Deleted %d files. Errors: %s',
						$deleteResponse->count,
						json_encode($deleteResponse->errors)
					)
				);
			}
			if ($deleteResponse->count === 0) {
				$response->errors[] = 'Failed to delete photo ' . $photo->identifier;
				$command->output?->writeln('<error>Failed to delete photo ' . $photo->identifier . '</error>');
				continue;
			}
			$response->deletedPhotos++;
		}

		// Find archives to delete
		$archives = new PhotoArchivesToDeleteQuery($command->arena)->noCache()->get();
		$command->output?->writeln(sprintf('Found %d archives to delete', count($archives)));

		// Delete archives
		foreach ($archives as $archive) {
			if ($command->output?->isVerbose() ?? false) {
				$command->output->writeln(
					sprintf(
						'Deleting archive #%d %s: %s',
						$archive->id,
						$archive->identifier,
						$archive->createdAt->format('Y-m-d H:i:s')
					)
				);
			}
			if ($command->dryRun) {
				$response->archivesDebug[] = $archive;
				continue;
			}

			$deleteResponse = $this->commandBus->dispatch(
				new RemoveFilesFromS3Command([$archive->identifier], $command->arena->photosSettings->bucket)
			);
			foreach ($deleteResponse->Errors as $error) {
				$response->errors[] = $error->Message;
				$command->output?->writeln('<error>' . $error->Message . '</error>');
			}
			if (count($deleteResponse->Deleted) === 1) {
				if (!$archive->delete()) {
					$response->errors[] = 'Failed to delete archive from DB ' . $archive->identifier;
					$command->output?->writeln(
						'<error>Failed to delete archive from DB ' . $archive->identifier . '</error>'
					);
				}
				$response->deletedArchives++;
			}
		}

		return $response;
	}
}