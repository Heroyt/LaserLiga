<?php
declare(strict_types=1);

namespace App\CQRS\Commands\S3;

use App\CQRS\CommandHandlers\S3\RemoveFilesFromS3CommandHandler;
use App\CQRS\CommandResponses\S3\DeleteObjectsResponse;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<DeleteObjectsResponse>
 */
class RemoveFilesFromS3Command implements CommandInterface
{
	/**
	 * @param non-empty-array<non-empty-string> $identifiers
	 */
	public function __construct(
		public array $identifiers,
		public ?string $bucket = null,
	) {}


	/**
	 * @inheritDoc
	 */
	public function getHandler(): string {
		return RemoveFilesFromS3CommandHandler::class;
	}
}