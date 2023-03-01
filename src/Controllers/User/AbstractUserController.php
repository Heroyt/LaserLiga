<?php

namespace App\Controllers\User;

use App\Models\Auth\User;
use Lsr\Core\Controller;

abstract class AbstractUserController extends Controller
{

	protected function getUser(string $code) : User {
		try {
			$user = User::getByCode(strtoupper($code));
		} catch (\InvalidArgumentException) {
			$this->params['errors'][] = lang('Kód není platný');
		}
		if (!isset($user)) {
			if ($this->request->isAjax()) {
				$this->params['errors'][] = 'User not found';
				$this->respond(['errors' => $this->params['errors']], 404);
			}
			http_response_code(404);
			$this->view('pages/profile/notFound');
			exit;
		}
		return $user;
	}

}