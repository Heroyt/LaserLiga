<?php
declare(strict_types=1);

namespace App\Core\ParamValidators;

use Lsr\Core\Routing\Interfaces\RouteParamValidatorInterface;
use Lsr\Orm\Model;

readonly class ModelSlugValidator implements RouteParamValidatorInterface
{

	/**
	 * @param class-string<Model>|null $model
	 */
	public function __construct(
		private ?string $model = null,
	) {}

	/**
	 * @inheritDoc
	 */
	public function validate(mixed $value): bool {
		if (!is_string($value) && !is_numeric($value)) {
			return false;
		}
		if ($this->model === null || !method_exists($this->model, 'existsSlug')) {
			return true;
		}
		return $this->model::existsSlug((int) $value);
	}
}