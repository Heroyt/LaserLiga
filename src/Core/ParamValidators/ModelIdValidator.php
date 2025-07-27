<?php
declare(strict_types=1);

namespace App\Core\ParamValidators;

use Lsr\Core\Routing\Interfaces\RouteParamValidatorInterface;
use Lsr\Orm\Model;

class ModelIdValidator implements RouteParamValidatorInterface
{

	/**
	 * @param class-string<Model>|null $model
	 */
	public function __construct(
		private readonly ?string $model = null,
	) {}

	/**
	 * @inheritDoc
	 */
	public function validate(mixed $value): bool {
		if (!is_numeric($value)) {
			return false;
		}
		if ($this->model === null) {
			return true;
		}
		return $this->model::exists((int) $value);
	}
}