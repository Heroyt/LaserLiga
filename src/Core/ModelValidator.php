<?php

namespace App\Core;

use App\Exceptions\ValidationException;
use App\Tools\Strings;
use InvalidArgumentException;
use Nette\Utils\Validators;

class ModelValidator
{

	/**
	 * @param null     $value Value to test
	 * @param string[] $validators
	 *
	 * @throws ValidationException
	 */
	public static function validateValue($value = null, array $validators = []) : void {
		foreach ($validators as $validator) {
			$explode = explode(':', $validator);
			$params = [];
			if (count($explode) === 2) {
				$validator = $explode[0];
				$params = explode(',', $explode[1]);
			}
			$validatorMethod = Strings::toCamelCase('validate_'.$validator);
			if (!method_exists(self::class, $validatorMethod)) {
				throw new ValidationException('Validator method: '.self::class.'::'.$validatorMethod.'() does not exist.');
			}
			// Validate the value
			self::$validatorMethod($value, ...$params);
		}
	}

	/**
	 * @param mixed|null $value
	 *
	 * @throws ValidationException
	 */
	public static function validateRequired(mixed $value = null) : void {
		if (!isset($value)) {
			throw new ValidationException('Value is required.');
		}
	}

	/**
	 * @param mixed|null $value
	 *
	 * @throws ValidationException
	 */
	public static function validateEmail(mixed $value = null) : void {
		if (!Validators::isEmail($value)) {
			throw new ValidationException('Value must be a valid email address.');
		}
	}

	/**
	 * @param mixed|null $value
	 * @param int|null   $length1
	 * @param int|null   $length2
	 *
	 * @throws ValidationException
	 */
	public static function validateString(mixed $value = null, ?int $length1 = null, ?int $length2 = null) : void {
		if (!is_string($value)) {
			throw new ValidationException('Value must be a string.');
		}
		if (!isset($length1) && !isset($length2)) {
			return;
		}
		$len = strlen($value);
		if (isset($length1, $length2) && ($len < $length1 || $len > $length2)) {
			throw new ValidationException('Value\'s length must be between '.$length1.' and '.$length2.'.');
		}
		else if (isset($length1) && !isset($length2) && $len !== $length1) {
			throw new ValidationException('Value\'s length must be '.$length1.'.');
		}
		else if (!isset($length1) && isset($length2) && $len !== $length2) {
			throw new ValidationException('Value\'s length must be '.$length2.'.');
		}
	}

	/**
	 * @param mixed|null $value
	 *
	 * @throws ValidationException
	 */
	public static function validateInt(mixed $value = null) : void {
		if (!is_int($value)) {
			throw new ValidationException('Value must be an int.');
		}
	}

	/**
	 * @param mixed|null $value
	 *
	 * @throws ValidationException
	 */
	public static function validateFloat(mixed $value = null) : void {
		if (!is_float($value)) {
			throw new ValidationException('Value must be a float.');
		}
	}

	/**
	 * @param mixed|null $value
	 *
	 * @throws ValidationException
	 */
	public static function validateNumeric(mixed $value = null) : void {
		if (!is_numeric($value)) {
			throw new ValidationException('Value must be numeric.');
		}
	}

	/**
	 * @param mixed|null $value
	 *
	 * @throws ValidationException
	 */
	public static function validateArray(mixed $value = null) : void {
		if (!is_array($value)) {
			throw new ValidationException('Value must be an array.');
		}
	}

	/**
	 * @param mixed|null $value
	 *
	 * @throws ValidationException
	 */
	public static function validateObject(mixed $value = null) : void {
		if (!is_object($value)) {
			throw new ValidationException('Value must be an object.');
		}
	}

	/**
	 * @param mixed|null $value
	 *
	 * @throws ValidationException
	 */
	public static function validateInstanceOf(mixed $value = null, string $className = '') : void {
		if (empty($className)) {
			throw new InvalidArgumentException('Class name is a required argument for instanceof validation.');
		}
		if (!$value instanceof $className) {
			throw new ValidationException('Value must be an instance of '.$className.'.');
		}
	}

}