<?php

namespace App\Core\Middleware;

use App\Models\Auth\User;
use Lsr\Core\App;
use Lsr\Core\Requests\Request;
use Lsr\Interfaces\RequestInterface;

class LoggedIn extends \Lsr\Core\Auth\Middleware\LoggedIn
{

	/**
	 * @param Request $request
	 *
	 * @return bool
	 */
	public function handle(RequestInterface $request) : bool {
		if (!User::loggedIn()) {
			$request->passErrors[] = lang('Pro přístup na tuto stránku se musíte přihlásit!', context: 'errors');
			App::redirect('login', $request);
		}
		if (!empty($this->rights)) {
			/** @var User $user */
			$user = User::getLoggedIn();
			$allow = true;
			foreach ($this->rights as $right) {
				if (!$user->hasRight($right)) {
					$allow = false;
					break;
				}
			}
			if (!$allow) {
				$request->passErrors[] = lang('You don\'t have permission to access this page.', context: 'errors');
				App::redirect([], $request);
			}
		}
		return true;
	}

}