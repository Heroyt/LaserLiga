<?php

declare(strict_types=1);

namespace App\CQRS\CommandHandlers\Google;

use App\CQRS\CommandResponses\Google\UpdateCalendarEventCommandResponse;
use App\CQRS\Commands\Google\UpdateCalendarEventCommand;
use Google\Service\Calendar as CalendarService;
use Google\Service\Calendar\Event as CalendarEvent;
use Google\Service\Exception;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;

final readonly class UpdateCalendarEventCommandHandler implements CommandHandlerInterface
{
	/**
	 * @param UpdateCalendarEventCommand $command
	 */
	public function handle(CommandInterface $command): UpdateCalendarEventCommandResponse {
		$service = new CalendarService($command->client);

		$data = [
			'summary' => $command->summary,
			'start'   => [
				'dateTime' => $command->start->format('Y-m-d\TH:i:s'),
				'timeZone' => $command->start->getTimezone()->getName(),
			],
			'end'     => [
				'dateTime' => $command->end->format('Y-m-d\TH:i:s'),
				'timeZone' => $command->end->getTimezone()->getName(),
			],
		];

		if (!empty($command->description)) {
			$data['description'] = $command->description;
		}
		if (!empty($command->location)) {
			$data['location'] = $command->location;
		}
		if (!empty($command->attendees)) {
			$data['attendees'] = array_map(
				static fn(string $email) => ['email' => $email],
				$command->attendees,
			);
		}

		$event = new CalendarEvent($data);
		$event->setId($command->eventId);

		try {
			$service->events->update($command->calendarId, $command->eventId, $event);
		} catch (Exception $e) {
			return new UpdateCalendarEventCommandResponse(
				success  : false,
				error    : $e->getMessage(),
				exception: $e,
			);
		}

		return new UpdateCalendarEventCommandResponse(
			success: true,
		);
	}
}
