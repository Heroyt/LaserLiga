<?php
declare(strict_types=1);

namespace App\CQRS\Commands\S3;

use App\CQRS\CommandHandlers\S3\CreatePhotosArchiveCommandHandler;
use App\Models\Arena;
use App\Models\Photos\Photo;
use App\Models\Photos\PhotoArchive;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<PhotoArchive|null>
 */
class CreatePhotosArchiveCommand implements CommandInterface
{

	/**
	 * @param non-empty-list<Photo> $photos
	 */
	public function __construct(
		public array $photos,
		public Arena $arena,
	){}


	/**
	 * @inheritDoc
	 */
	public function getHandler(): string {
		return CreatePhotosArchiveCommandHandler::class;
	}
}