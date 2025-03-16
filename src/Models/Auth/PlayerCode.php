<?php

namespace App\Models\Auth;

use Attribute;
use Lsr\ObjectValidation\Attributes\Validator;
use Lsr\Orm\Exceptions\ValidationException;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class PlayerCode implements Validator
{

	/**
	 * Validate a value and throw an exception on error
	 *
	 *
	 * @param mixed                       $value
	 * @param Player|class-string<Player> $class
	 * @param string                      $property
	 * @param string                      $propertyPrefix *
	 *
	 * @return void
	 *
	 * @throws ValidationException
	 */
	public function validateValue(mixed $value, object|string $class, string $property, string $propertyPrefix = ''): void {
		$class::validateCode($value, is_string($class) ? new $class : $class);
	}
}