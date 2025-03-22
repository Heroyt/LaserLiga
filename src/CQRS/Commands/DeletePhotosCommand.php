<?php
declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\DeletePhotosCommandHandler;
use App\CQRS\CommandResponses\DeletePhotosResponse;
use App\Models\Photos\Photo;
use Lsr\CQRS\CommandInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @implements CommandInterface<DeletePhotosResponse>
 */
final readonly class DeletePhotosCommand implements CommandInterface
{
	/**
	 * @param non-empty-list<Photo> $photos
	 */
	public function __construct(
		public array $photos,
		public ?OutputInterface $output = null,
	){}

	/**
	 * @inheritDoc
	 */
	public function getHandler(): string {
		return DeletePhotosCommandHandler::class;
	}
}