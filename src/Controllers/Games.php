<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\GameModels\Factory\GameFactory;

class Games extends Controller
{

	/**
	 * @param Request $request
	 *
	 * @return void
	 */
	public function show(Request $request) : void {
		$gameCode = $request->params['game'] ?? '';
		$this->params['game'] = GameFactory::getByCode($gameCode);
		if (!isset($this->params['game'])) {
			http_response_code(404);
			$this->view('pages/game/empty');
			return;
		}
		$this->view('pages/game/index');
	}

}