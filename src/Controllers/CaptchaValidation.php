<?php
declare(strict_types=1);

namespace App\Controllers;

use Lsr\Core\Controllers\TemplateParameters;
use Lsr\Core\Requests\Request;

trait CaptchaValidation
{
	protected function validateCaptcha(Request $request) : bool {
		$token = $request->getPost($this->turnstile::INPUT_NAME, '');
		if (empty($token) || !is_string($token) || !$this->turnstile->validate($token)) {
			/** @phpstan-ignore instanceof.alwaysTrue */
			if ($this->params instanceof TemplateParameters) {
				$this->params->errors[] = lang('Jste člověk? Prosím načtěte stránku a vyplňte formulář znovu.', context: 'errors');
			}
			else {
				$this->params['errors'][] = lang('Jste člověk? Prosím načtěte stránku a vyplňte formulář znovu.', context: 'errors');
			}
			return false;
		}
		return true;
	}

}