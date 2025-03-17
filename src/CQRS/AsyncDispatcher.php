<?php
declare(strict_types=1);

namespace App\CQRS;

use Lsr\CQRS\CommandBus;
use Lsr\Logging\Logger;

final readonly class AsyncDispatcher
{

	public function __construct(
		private AsyncCommandBus $asyncBus,
		private CommandBus $commandBus,
		private Logger $logger,
	) {}

	public function dispatchAsyncQueue(): void {
		foreach ($this->asyncBus->queue as $command) {
			try {
				$this->commandBus->dispatch($command);
			} catch (\Throwable $e) {
				$this->logger->exception($e);
			}
		}
	}

}