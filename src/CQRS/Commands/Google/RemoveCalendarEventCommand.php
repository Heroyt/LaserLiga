<?php

declare(strict_types=1);

namespace App\CQRS\Commands\Google;

use App\CQRS\CommandHandlers\Google\RemoveCalendarEventCommandHandler;
use Google\Client;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<RemoveCalendarEventCommandHandler>
 */
final readonly class RemoveCalendarEventCommand implements CommandInterface
{

	public function __construct(
		public Client $client,
		public string $calendarId,
		public string $eventId,
	) {
	}


	public function getHandler(): string {
		return RemoveCalendarEventCommandHandler::class;
	}
}
