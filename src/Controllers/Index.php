<?php

namespace App\Controllers;

use App\GameModels\Factory\GameFactory;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Tournament\Tournament;
use Lsr\Core\Controller;

class Index extends Controller
{

	public function show(): void {
		$this->params['breadcrumbs'] = [
			'Laser Liga' => [],
		];
		$this->title = 'Portál pro hráče laser game';
		$this->description = 'Portál pro hráče laser game. Výsledky ze hry, turnaje, statistiky...';
		$this->params['addCss'][] = 'pages/index.css';
		$this->params['playerCount'] = LigaPlayer::query()->count();
		$this->params['arenaCount'] = Arena::query()->count();
		$this->params['tournamentCount'] = Tournament::query()->count();
		$this->params['gameCount'] = GameFactory::queryGames(true)->count();
		$this->view('pages/index/index');
	}

}