<?php

namespace App\Logging\Tracy;

use App\Core\Auth\User;
use Tracy\IBarPanel;

class LoginTracyPanel implements IBarPanel
{

	/**
	 * @inheritDoc
	 */
	public function getTab() : string {
		return view('debug/Login/tab', [], true);
	}

	/**
	 * @inheritDoc
	 */
	public function getPanel() : string {
		return view('debug/Login/panel', [
			'user' => User::getLoggedIn(),
		],          true);
	}

}