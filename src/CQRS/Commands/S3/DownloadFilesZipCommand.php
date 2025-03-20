<?php
declare(strict_types=1);

namespace App\CQRS\Commands\S3;

use App\CQRS\CommandHandlers\S3\DownloadFilesZipCommandHandler;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<bool>
 */
final readonly class DownloadFilesZipCommand implements CommandInterface
{

	/**
	 * @param non-empty-string[] $urls   List of file URLs to download
	 * @param non-empty-string   $outZip Output zip file path
	 */
	public function __construct(
		public array  $urls,
		public string $outZip,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getHandler(): string {
		return DownloadFilesZipCommandHandler::class;
	}
}