<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\CommandResponses\DeletePhotosResponse;
use App\CQRS\Commands\DeletePhotosCommand;
use App\CQRS\Commands\S3\RemoveFilesFromS3Command;
use App\Models\Photos\Photo;
use App\Models\Photos\PhotoVariation;
use Lsr\CQRS\CommandBus;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;

final readonly class DeletePhotosCommandHandler implements CommandHandlerInterface
{
	public function __construct(
		private CommandBus $commandBus,
	) {
	}


	/**
	 * @param DeletePhotosCommand $command
	 */
	public function handle(CommandInterface $command): DeletePhotosResponse {
		$response = new DeletePhotosResponse();

		foreach ($command->photos as $photo) {
			$files = [
				$photo->identifier => $photo,
			];
			foreach ($photo->variations as $variation) {
				$files[$variation->identifier] = $variation;
			}

			$result = $this->commandBus->dispatch(new RemoveFilesFromS3Command(array_keys($files)));
			bdump($result);
			foreach ($result->Deleted as $deleted) {
				$file = $files[$deleted->Key] ?? null;
				if ($file instanceof PhotoVariation) {
					$file->delete();
				}
			}
			if ($command->output !== null) {
				foreach ($result->Errors as $error) {
					$response->errors[] = $error->Message;
					$command->output->writeln('<error>' . $error->Message . '</error>');
				}
			}

			if (count($result->Deleted) === count($files)) {
				$photo->delete();
				$response->count++;
			}
			else {
				$response->errors[] = 'Failed to delete photo ' . $photo->identifier;
				$command->output?->writeln('<error>Failed to delete photo ' . $photo->identifier . '</error>');
			}
		}

		Photo::clearQueryCache();

		return $response;
	}
}