<?php

namespace App\Exceptions;

use Exception;

class InsufficientRegressionDataException extends Exception
{

	public function __construct(string $model, ?string $query = null) {
		parent::__construct(
			'Insufficient regression data for model: ' . $model.
			($query ? ' Query: ' . $query : '')
		);
	}

}