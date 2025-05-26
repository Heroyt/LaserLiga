<?php
declare(strict_types=1);

namespace App\CQRS\Commands\S3;


use App\CQRS\CommandHandlers\S3\UploadFileToS3CommandHandler;
use App\CQRS\CommandResponses\S3\PutObjectResponse;
use App\CQRS\Enums\S3\StorageClass;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<PutObjectResponse>
 */
readonly final class UploadFileToS3Command implements CommandInterface
{

	public function __construct(
		public string $filename,
		public ?string $identifier = null,
		public ?string $bucket = null,
		public ?StorageClass $storageClass = null,
	) {
		if (!file_exists($this->filename)) {
			throw new \InvalidArgumentException('File not found');
		}
		if (!is_readable($this->filename)) {
			throw new \InvalidArgumentException('File not readable');
		}
	}

	public function getHandler(): string {
		return UploadFileToS3CommandHandler::class;
	}
}