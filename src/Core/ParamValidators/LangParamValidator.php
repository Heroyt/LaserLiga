<?php
declare(strict_types=1);

namespace App\Core\ParamValidators;

use Lsr\Core\App;
use Lsr\Core\Routing\Interfaces\RouteParamValidatorInterface;

class LangParamValidator implements RouteParamValidatorInterface
{

	/**
	 * @inheritDoc
	 */
	public function validate(mixed $value): bool {
		return
			is_string($value)
			&& !empty($value)
			&& App::getInstance()->translations->supportsLanguage($value);
	}
}