<?php


namespace App\Logging;

use Exception;
use Throwable;

/**
 * Class DirectoryCreationException
 *
 * @package eSoul\Logging
 */
class DirectoryCreationException extends Exception
{

	public function __construct(string $path, Throwable $previous = null) {
		parent::__construct(sprintf('Failed creating logging directory: %s', $path), 0, $previous);
	}
}