<?php
declare(strict_types=1);

namespace App\CQRS\Commands\Dropbox;

use App\CQRS\CommandHandlers\Dropbox\DeleteDropboxFileCommandHandler;
use App\CQRS\CommandResponses\Dropbox\FileDeleteResponse;
use Lsr\CQRS\CommandInterface;
use Spatie\Dropbox\Client;

/**
 * @implements CommandInterface<FileDeleteResponse>
 */
final readonly class DeleteDropboxFileCommand implements CommandInterface
{

	public function __construct(
		public Client $client,
		public string $path,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getHandler(): string {
		return DeleteDropboxFileCommandHandler::class;
	}
}