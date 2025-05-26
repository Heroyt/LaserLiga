<?php
declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\AutoDeletePhotosCommandHandler;
use App\CQRS\CommandResponses\AutoDeletePhotosResponse;
use App\Models\Arena;
use Lsr\CQRS\CommandInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @implements CommandInterface<AutoDeletePhotosResponse>
 */
final readonly class AutoDeletePhotosCommand implements CommandInterface
{

	public function __construct(
		public Arena $arena,
		public bool $dryRun = false,
		public ?OutputInterface $output = null,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getHandler(): string {
		return AutoDeletePhotosCommandHandler::class;
	}
}