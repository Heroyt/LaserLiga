<?php

declare(strict_types=1);

namespace App\CQRS\CommandHandlers\Google;

use App\CQRS\CommandResponses\Google\RemoveCalendarEventCommandResponse;
use App\CQRS\Commands\Google\RemoveCalendarEventCommand;
use Google\Service\Calendar as CalendarService;
use Google\Service\Exception;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;

final readonly class RemoveCalendarEventCommandHandler implements CommandHandlerInterface
{
	/**
	 * @param RemoveCalendarEventCommand $command
	 */
	public function handle(CommandInterface $command): RemoveCalendarEventCommandResponse
	{
		$service = new CalendarService($command->client);

		try {
			$service->events->delete($command->calendarId, $command->eventId);
		} catch (Exception $e) {
			return new RemoveCalendarEventCommandResponse(
				success: false,
				error: $e->getMessage(),
				exception: $e,
			);
		}

		return new RemoveCalendarEventCommandResponse(
			success: true,
		);
	}
}
