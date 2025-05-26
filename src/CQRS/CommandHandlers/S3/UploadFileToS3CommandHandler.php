<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers\S3;

use App\CQRS\CommandResponses\S3\PutObjectResponse;
use App\CQRS\Commands\S3\UploadFileToS3Command;
use App\CQRS\Enums\S3\StorageClass;
use App\Services\AWS\S3Config;
use Aws\Result;
use Aws\S3\S3Client;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Serializer\Mapper;

final readonly class UploadFileToS3CommandHandler implements CommandHandlerInterface
{

	public function __construct(
		private S3Client $s3Client,
		private S3Config $config,
		private Mapper   $mapper,
	) {
	}

	/**
	 * @inheritDoc
	 *
	 * @param UploadFileToS3Command $command
	 */
	public function handle(CommandInterface $command): PutObjectResponse {
		$result = $this->s3Client->putObject(
			[
				'Bucket'       => $command->bucket ?? $this->config->bucket,
				'Key'          => $command->identifier ?? $command->filename,
				'Body'         => fopen($command->filename, 'rb'),
				'ACL'          => 'public-read',
				'StorageClass' => $command->storageClass?->value ?? StorageClass::STANDARD->value,
			]
		);

		return $this->mapResponse($result);
	}

	private function mapResponse(Result $response): PutObjectResponse {
		$data = $response->toArray();
		if (isset($data['@metadata'])) {
			$data['metadata'] = $data['@metadata'];
		}
		return $this->mapper->map($data, PutObjectResponse::class);
	}
}