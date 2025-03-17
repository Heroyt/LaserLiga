<?php
declare(strict_types=1);

namespace App\CQRS;

use Lsr\CQRS\AsyncCommandBusInterface;
use Lsr\CQRS\CommandInterface;

final class AsyncCommandBus implements AsyncCommandBusInterface
{

	/** @var CommandInterface[] */
	private(set) array $queue = [];

	/**
	 * @inheritDoc
	 */
	public function dispatch(CommandInterface $command): void {
		$this->queue[] = $command;
	}
}