<?php

declare(strict_types=1);

namespace App\CQRS\Commands\Google;

use App\CQRS\CommandHandlers\Google\CreateCalendarCommandHandler;
use Google\Client;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<CreateCalendarCommandHandler>
 */
final readonly class CreateCalendarCommand implements CommandInterface
{
	public function __construct(
		public Client $client,
		public string $summary,
		public string $timeZone = 'Europe/Prague',
	) {
	}


	public function getHandler(): string {
		return CreateCalendarCommandHandler::class;
	}
}
