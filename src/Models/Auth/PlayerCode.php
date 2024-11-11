<?php

namespace App\Models\Auth;

use Attribute;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Validation\Validator;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class PlayerCode implements Validator
{

	/**
	 * Validate a value and throw an exception on error
	 *
	 * @param mixed             $value
	 * @param class-string<Player|LigaPlayer>|Player|LigaPlayer $class
	 * @param string            $property
	 *
	 * @return void
	 *
	 * @throws ValidationException
	 */
	public function validateValue(mixed $value, object|string $class, string $property) : void {
		$class::validateCode($value, is_string($class) ? new $class : $class);
	}
}