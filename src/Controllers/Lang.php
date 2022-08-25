<?php

namespace App\Controllers;

use Lsr\Core\App;
use Lsr\Core\Controller;
use Lsr\Core\Requests\Request;

class Lang extends Controller
{

	public function setLang(Request $request) : never {
		$_SESSION['lang'] = $request->params['lang'];
		App::redirect($request->get['redirect'] ?? []);
	}

}