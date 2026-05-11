<?php
declare(strict_types=1);

namespace App\Core\ObjectValidators;

use Attribute;
use Lsr\ObjectValidation\Attributes\Validator;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Model;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
readonly class ModelId implements Validator
{

	/**
	 * @param class-string<Model> $model
	 */
	public function __construct(
		protected string $model,
		protected ?string $message = null,
	) {
	}

	public function validateValue(mixed $value, object|string $class, string $property, string $propertyPrefix = ''): void {
		if (!is_numeric($value)) {
			throw $this->message !== null ?
				ValidationException::createWithCustomMessage($class, $property, $this->message)
				: ValidationException::createWithValue(
					$class,
					$propertyPrefix . $property,
					'Must be a numeric model ID. (value: %s)',
					$value
				);
		}

		$id = (int)$value;
		if (!$this->model::exists($id)) {
			throw $this->message !== null ?
				ValidationException::createWithCustomMessage($class, $property, $this->message)
				: ValidationException::createWithValue(
					$class,
					$propertyPrefix . $property,
					'Model ID does not exist. (value: %s)',
					$value
				);
		}
	}
}