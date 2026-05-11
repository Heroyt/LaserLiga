<?php
declare(strict_types=1);

namespace App\Core\ObjectValidators;

use Attribute;
use Lsr\ObjectValidation\Attributes\Validator;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\ObjectValidation\Exceptions\ValidationMultiException;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
readonly class ValidateEach implements Validator
{

	public function validateValue(mixed $value, object|string $class, string $property, string $propertyPrefix = ''): void {
		if (!is_array($value)) {
			return;
		}

		$validator = new \Lsr\ObjectValidation\Validator();
		$exceptions = [];

		foreach ($value as $key => $item) {
			try {
				$validator->validateAll($item, $propertyPrefix . $property . '[' . $key . '].');
			} catch (ValidationException $e) {
				$exceptions[] = $e;
			}
		}

		if (count($exceptions) === 1) {
			throw $exceptions[0];
		}
		if (count($exceptions) > 1) {
			throw new ValidationMultiException($exceptions);
		}
	}
}