<?php

declare(strict_types=1);

namespace App\CQRS\CommandHandlers\Google;

use App\CQRS\CommandResponses\Google\CreateCalendarCommandResponse;
use App\CQRS\Commands\Google\CreateCalendarCommand;
use Google\Service\Calendar as CalendarService;
use Google\Service\Calendar\Calendar;
use Google\Service\Exception;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;

final readonly class CreateCalendarCommandHandler implements CommandHandlerInterface
{
	/**
	 * @param CreateCalendarCommand $command
	 */
	public function handle(CommandInterface $command): CreateCalendarCommandResponse
	{
		$service = new CalendarService($command->client);

		$calendar = new Calendar();
		$calendar->setSummary($command->summary);
		$calendar->setTimeZone($command->timeZone);

		try {
			$response = $service->calendars->insert($calendar);
		} catch (Exception $e) {
			return new CreateCalendarCommandResponse(
				success: false,
				error: $e->getMessage(),
				exception: $e,
			);
		}

		return new CreateCalendarCommandResponse(
			success: true,
			calendar: $response,
		);
	}
}
