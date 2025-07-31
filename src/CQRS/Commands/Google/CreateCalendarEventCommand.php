<?php

declare(strict_types=1);

namespace App\CQRS\Commands\Google;

use App\CQRS\CommandHandlers\Google\CreateCalendarEventCommandHandler;
use DateTimeInterface;
use Google\Client;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<CreateCalendarEventCommandHandler>
 */
final readonly class CreateCalendarEventCommand implements CommandInterface
{
	/**
	 * @param string[]|null $attendees List of email addresses of attendees
	 */
	public function __construct(
		public Client            $client,
		public string            $calendarId,
		public string            $summary,
		public DateTimeInterface $start,
		public DateTimeInterface $end,
		public string            $description = '',
		public ?string           $location = null,
		public ?array            $attendees = null,
	) {
	}


	public function getHandler(): string {
		return CreateCalendarEventCommandHandler::class;
	}
}
