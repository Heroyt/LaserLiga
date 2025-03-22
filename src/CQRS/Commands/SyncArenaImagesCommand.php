<?php
declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\SyncArenaImagesCommandHandler;
use App\CQRS\CommandResponses\SyncArenaImagesResponse;
use App\Models\Arena;
use Lsr\CQRS\CommandInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @implements CommandInterface<SyncArenaImagesResponse>
 */
final readonly class SyncArenaImagesCommand implements CommandInterface
{

	/**
	 * @param list<int<1,max>> $optimizeSizes
	 */
	public function __construct(
		public Arena $arena,
		public ?int $limit = null,
		public array $optimizeSizes = [
			150,
		],
		public ?OutputInterface $output = null,
	) {
	}


	/**
	 * @inheritDoc
	 */
	public function getHandler(): string {
		return SyncArenaImagesCommandHandler::class;
	}
}