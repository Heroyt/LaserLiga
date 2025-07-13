<?php

namespace App\Controllers;

use App\GameModels\Factory\GameFactory;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Tournament\Tournament;
use App\Templates\Index\IndexParameters;
use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

/**
 * @property IndexParameters $params
 */
class Index extends Controller
{

	public function __construct() {
		parent::__construct();
		$this->params = new IndexParameters();
	}

	public function show(): ResponseInterface {
		$this->params->breadcrumbs = [
			'Laser Liga' => [],
		];
		$this->title = 'Portál pro hráče laser game';
		$this->description = 'Laser liga je portál pro hráče laser game z celé české republiky. Sjednocuje výsledky a poskytuje statistiky hráčům, kteří soupeří v globálním žebříčku.';
		$this->params->addCss[] = 'pages/index.css';
		$this->params->playerCount = LigaPlayer::query()->count();
		$this->params->arenaCount = Arena::query()->where('hidden = 0')->count();
		$this->params->tournamentCount = Tournament::query()->count();
		$this->params->gameCount = GameFactory::queryGames(true)->count();
		return $this->view('pages/index/index');
	}

}