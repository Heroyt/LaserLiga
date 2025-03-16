<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers\Dropbox;

use App\CQRS\Commands\Dropbox\DownloadDropboxFileCommand;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;

class DownloadDropboxFileCommandHandler implements CommandHandlerInterface
{

	/**
	 * @inheritDoc
	 * @param DownloadDropboxFileCommand $command
	 */
	public function handle(CommandInterface $command): bool {
		$resource = $command->client->download($command->path);
		return file_put_contents($command->destination, $resource) !== false;
	}
}