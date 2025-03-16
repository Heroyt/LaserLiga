<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers\Dropbox;

use App\CQRS\CommandResponses\Dropbox\FileDeleteResponse;
use App\CQRS\Commands\Dropbox\DeleteDropboxFileCommand;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Serializer\Mapper;

final readonly class DeleteDropboxFileCommandHandler implements CommandHandlerInterface
{

	public function __construct(
		private Mapper $mapper,
	){}

	/**
	 * @param DeleteDropboxFileCommand $command
	 */
	public function handle(CommandInterface $command): FileDeleteResponse {
		$response = $command->client->delete($command->path);
		return $this->mapper->map($this->prepareResponse($response), FileDeleteResponse::class);
	}

	/**
	 * @param array<string,mixed> $response
	 * @return array<string,mixed>
	 */
	private function prepareResponse(array $response) : array {
		if (isset($response['.tag'])) {
			$response['tag'] = $response['.tag'];
			unset($response['.tag']);
		}
		if (isset($response['error']['.tag'])) {
			$response['error']['tag'] = $response['error']['.tag'];
			unset($response['error']['.tag']);
		}
		if (isset($response['metadata']['.tag'])) {
			$response['metadata']['tag'] = $response['metadata']['.tag'];
			unset($response['metadata']['.tag']);
		}
		return $response;
	}
}