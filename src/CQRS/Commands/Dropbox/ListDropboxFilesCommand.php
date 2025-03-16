<?php
declare(strict_types=1);

namespace App\CQRS\Commands\Dropbox;

use App\CQRS\CommandHandlers\Dropbox\ListDropboxFilesCommandHandler;
use App\CQRS\CommandResponses\Dropbox\FileMetadata;
use Generator;
use Lsr\CQRS\CommandInterface;
use Spatie\Dropbox\Client;

/**
 * @implements CommandInterface<Generator<FileMetadata>>
 */
final readonly class ListDropboxFilesCommand implements CommandInterface
{

	/**
	 * @param non-empty-string                  $path
	 * @param non-empty-lowercase-string[]|null $typeFilter
	 */
	public function __construct(
		public Client $client,
		public string $path,
		public bool   $recursive = true,
		public ?array $typeFilter = null,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getHandler(): string {
		return ListDropboxFilesCommandHandler::class;
	}
}