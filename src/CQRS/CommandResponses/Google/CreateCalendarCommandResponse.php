<?php

declare(strict_types=1);

namespace App\CQRS\CommandResponses\Google;

use Google\Service\Calendar\Calendar;
use Google\Service\Exception;

final readonly class CreateCalendarCommandResponse
{

	/**
	 * @param bool                                  $success
	 * @param ($success is true ? null : string)    $error
	 * @param ($success is true ? null : Exception) $exception
	 * @param ($success is true ? Calendar : null)  $calendar
	 */
	public function __construct(
		public bool       $success = true,
		public ?string    $error = null,
		public ?Exception $exception = null,
		public ?Calendar  $calendar = null,
	) {
	}
}
