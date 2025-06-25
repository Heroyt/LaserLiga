<?php

namespace App\Controllers\User;

use App\Exceptions\DispatchBreakException;
use App\Models\Auth\User;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;

abstract class AbstractUserController extends Controller
{

	protected function getUser(string $code): User {
		try {
			$user = User::getByCode(strtoupper($code));
		} catch (\InvalidArgumentException) {
			$this->params['errors'][] = lang('Kód není platný');
		}
		if (!isset($user)) {
			assert($this->request instanceof Request, 'Invalid request');
			if ($this->request->isAjax()) {
				$this->params['errors'][] = 'User not found';
				throw DispatchBreakException::create(
					new ErrorResponse('User not found', ErrorType::NOT_FOUND, values: $this->params['errors']),
					404
				);
			}
			throw new DispatchBreakException(
				$this->view('pages/profile/notFound')
				     ->withStatus(404)
			);
		}
		return $user;
	}

}