<?php

namespace App\Controllers;

use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
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
		/** @var string[]|string $to */
		$to = $request->getGet('redirect', []);
		return $this->app
			->redirect($to)
			->withAddedHeader('Set-Cookie', 'lang="' . $lang . '"; Max-Age=2592000');
	}

}