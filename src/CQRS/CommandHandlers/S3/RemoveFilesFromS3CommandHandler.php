<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers\S3;

use App\CQRS\CommandResponses\S3\DeleteObjectsResponse;
use App\CQRS\Commands\S3\RemoveFilesFromS3Command;
use App\Services\AWS\S3Config;
use Aws\Result;
use Aws\S3\S3Client;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Serializer\Mapper;

final readonly class RemoveFilesFromS3CommandHandler implements CommandHandlerInterface
{

	public function __construct(
		private S3Client $s3Client,
		private S3Config $config,
		private Mapper   $mapper,
	) {
	}

	/**
	 * @param RemoveFilesFromS3Command $command
	 */
	public function handle(CommandInterface $command): DeleteObjectsResponse {
		$objects = [];
		foreach ($command->identifiers as $identifier) {
			$objects[] = ['Key' => $identifier];
		}
		$result = $this->s3Client
			->deleteObjects([
				               'Bucket' => $this->config->bucket,
				               'Delete'    => [
								   'Objects' => $objects,
				               ],
			               ]);

		return $this->mapResponse($result);
	}

	private function mapResponse(Result $response) : DeleteObjectsResponse {
		$data = $response->toArray();
		return $this->mapper->map($data, DeleteObjectsResponse::class);
	}
}