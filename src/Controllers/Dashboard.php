<?php

namespace App\Controllers;

use App\Models\Auth\User;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controller;
use Lsr\Core\Templating\Latte;

class Dashboard extends Controller
{

	protected string $title       = 'Dashboard';
	protected string $description = '';

	/**
	 * @param Latte      $latte
	 * @param Auth<User> $auth
	 */
	public function __construct(
		protected Latte         $latte,
		protected readonly Auth $auth,
	) {
		parent::__construct($latte);
	}

	public function show() : void {
		$this->params['addCss'] = ['pages/playerProfile.css'];
		$this->params['loggedInUser'] = $this->params['user'] = $this->auth->getLoggedIn();
		$this->params['lastGames'] = $this->params['user']->player->queryGames()
																															->limit(10)
																															->orderBy('start')
																															->desc()
																															->cacheTags('user/games', 'user/'.$this->params['user']->id.'/games', 'user/'.$this->params['user']->id.'/lastGames')
																															->fetchAll();
		$this->view('pages/dashboard/index');
	}

	public function bp() : never {
		header('location: https://youtu.be/dQw4w9WgXcQ');
		exit;
	}

}