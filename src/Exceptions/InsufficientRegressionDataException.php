<?php

namespace App\Exceptions;

use Exception;

class InsufficientRegressionDataException extends Exception
{

	public function __construct(string $model) {
		parent::__construct('Insufficient regression data for model: ' . $model);
	}

}