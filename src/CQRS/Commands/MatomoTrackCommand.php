<?php
declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\MatomoTrackCommandHandler;
use Closure;
use Lsr\CQRS\CommandInterface;
use MatomoTracker;

/**
 * @implements CommandInterface<bool>
 */
final readonly class MatomoTrackCommand implements CommandInterface
{

	/** @var Closure(MatomoTracker $matomo):void */
	public Closure $callback;

	/**
	 * @param callable(MatomoTracker $matomo):void $callback
	 */
	public function __construct(
		callable $callback,
	) {
		$this->callback = $callback(...);
	}

	/**
	 * @inheritDoc
	 */
	public function getHandler(): string {
		return MatomoTrackCommandHandler::class;
	}
}