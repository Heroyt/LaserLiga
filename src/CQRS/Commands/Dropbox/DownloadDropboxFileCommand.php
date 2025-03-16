<?php
declare(strict_types=1);

namespace App\CQRS\Commands\Dropbox;

use App\CQRS\CommandHandlers\Dropbox\DownloadDropboxFileCommandHandler;
use Lsr\CQRS\CommandInterface;
use Spatie\Dropbox\Client;

/**
 * @implements CommandInterface<bool>
 */
final readonly class DownloadDropboxFileCommand implements CommandInterface
{
	public function __construct(
		public Client $client,
		public string $path,
		public string $destination,
	) {
	}


	/**
	 * @inheritDoc
	 */
	public function getHandler(): string {
		return DownloadDropboxFileCommandHandler::class;
	}
}