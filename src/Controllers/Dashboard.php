<?php

namespace App\Controllers;

use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controller;
use Lsr\Core\Templating\Latte;

class Dashboard extends Controller
{

	protected string $title       = 'Dashboard';
	protected string $description = '';

	public function __construct(
		protected Latte         $latte,
		protected readonly Auth $auth,
	) {
		parent::__construct($latte);
	}

	public function show() : void {
		$this->params['user'] = $this->auth->getLoggedIn();
		$this->view('pages/dashboard/index');
	}

	public function bp() : never {
		header('location: https://youtu.be/dQw4w9WgXcQ');
		exit;
	}

}