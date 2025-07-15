<?php
declare(strict_types=1);

namespace App\Core\AsyncHandlers;

use App\CQRS\AsyncDispatcher;
use Lsr\Core\Http\AsyncHandlerInterface;

final readonly class CqrsDispatcher implements AsyncHandlerInterface
{

	public function __construct(
		private AsyncDispatcher $dispatcher,
	){}

	public function run(): void {
		$this->dispatcher->dispatchAsyncQueue();
	}
}