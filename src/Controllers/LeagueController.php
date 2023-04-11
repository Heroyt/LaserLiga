<?php

namespace App\Controllers;

use App\Models\Tournament\League;
use Lsr\Core\Controller;

class LeagueController extends Controller
{

	public function detail(League $league) : void {
		$this->title = 'Liga %s';
		$this->titleParams[] = $league->name;
		$this->description = 'Liga %s v %s, aneb několik na sebe navazujících turnajů.';
		$this->descriptionParams[] = $league->name;
		$this->descriptionParams[] = $league->arena->name;
		$this->params['league'] = $league;

		$this->view('/pages/league/detail');
	}

}