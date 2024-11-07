<?php

namespace App\Controllers;

use Lsr\Core\App;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Session;
use Lsr\Interfaces\SessionInterface;
use Psr\Http\Message\ResponseInterface;

class Lang extends Controller
{
	public function __construct(
		private readonly SessionInterface $session,
	) {
		parent::__construct();
	}

	public function setLang(string $lang, Request $request) : ResponseInterface {
		$this->session->set('lang', $lang);
		return $this->app
			->redirect($request->getGet('redirect', []))
			->withAddedHeader('Set-Cookie', 'lang="' . $lang . '"; Max-Age=2592000');
	}

}