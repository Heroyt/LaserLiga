<?php

declare(strict_types=1);

namespace App\CQRS\CommandResponses\Booking;

use Exception;

final readonly class DeleteBookingCommandResponse
{
	/**
	 * @param bool                                  $success
	 * @param ($success is true ? null : string)    $error
	 * @param ($success is true ? null : Exception|null) $exception
	 */
	public function __construct(
		public bool $success = true,
		public ?string $error = null,
		public ?Exception $exception = null,
	)
	{
	}
}
