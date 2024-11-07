<?php
declare(strict_types=1);

namespace App\Controllers;

use Lsr\Core\Requests\Request;

trait CaptchaValidation
{
	protected function validateCaptcha(Request $request) : void {
		$token = (string) $request->getPost($this->turnstile::INPUT_NAME, '');
		if (!$this->turnstile->validate($token)) {
			$this->params['errors'][] = lang('Jste člověk? Prosím načtěte stránku a vyplňte formulář znovu.', context: 'errors');
		}
	}

}