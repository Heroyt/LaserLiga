<?php


namespace App\Core\Auth;


use App\Core\App;
use App\Core\Request;
use App\Core\Routing\Middleware;

class LoggedOut implements Middleware
{

	/**
	 * Handles a request - checks if the user is logged out
	 *
	 * @param Request $request
	 *
	 * @return bool
	 */
	public function handle(Request $request) : bool {
		if (User::loggedIn()) {
			App::redirect('admin', $request);
			return false;
		}
		return true;
	}

}
