<?php

declare(strict_types=1);

namespace App\CQRS\CommandResponses\Google;

use Google\Service\Exception;

final readonly class UpdateCalendarEventCommandResponse
{
	/**
	 * @param bool                                  $success
	 * @param ($success is true ? null : string)    $error
	 * @param ($success is true ? null : Exception) $exception
	 */
	public function __construct(
		public bool $success = true,
		public ?string $error = null,
		public ?Exception $exception = null,
	)
	{
	}
}
