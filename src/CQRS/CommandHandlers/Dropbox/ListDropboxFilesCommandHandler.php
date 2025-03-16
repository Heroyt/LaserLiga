<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers\Dropbox;

use App\CQRS\CommandResponses\Dropbox\FileMetadata;
use App\CQRS\CommandResponses\Dropbox\ListResponse;
use App\CQRS\Commands\Dropbox\ListDropboxFilesCommand;
use Generator;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Serializer\Mapper;

final readonly class ListDropboxFilesCommandHandler implements CommandHandlerInterface
{
	public function __construct(
		private Mapper $mapper,
	){}


	/**
	 * @param ListDropboxFilesCommand $command
	 *
	 * @return Generator<FileMetadata>
	 */
	public function handle(CommandInterface $command): Generator {
		$response = $command->client->listFolder($command->path, $command->recursive);
		$response = $this->mapper->map($this->prepareResponse($response), ListResponse::class);

		// Handle response
		$first = true;
		do {
			if (!$first) {
				// Load the next page
				$response = $command->client->listFolderContinue($response->cursor);
				$response = $this->mapper->map($this->prepareResponse($response), ListResponse::class);
			}
			$first = false;

			// Handle entries
			foreach ($response->entries as $entry) {
				// Filter out only files
				if (!$entry->isFile) {
					continue;
				}
				// Filter out file types
				if ($command->typeFilter !== null && !in_array($entry->fileType, $command->typeFilter, true)) {
					continue;
				}

				yield $entry;
			}
		} while ($response->hasMore);
	}

	/**
	 * @param array<string,mixed> $response
	 * @return array<string,mixed>
	 */
	private function prepareResponse(array $response) : array {
		if (isset($response['entries'])) {
			foreach ($response['entries'] as &$entry) {
				if (isset($entry['.tag'])) {
					$entry['tag'] = $entry['.tag'];
					unset($entry['.tag']);
				}
			}
		}
		return $response;
	}
}