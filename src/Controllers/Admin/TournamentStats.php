<?php

namespace App\Controllers\Admin;

use App\Models\Tournament\League;
use App\Models\Tournament\LeagueCategory;
use App\Models\Tournament\Stats;
use App\Models\Tournament\Tournament;
use Lsr\Core\Controller;

class TournamentStats extends Controller
{

	public function tournamentStats(Tournament $tournament): void {
		$this->params['tournament'] = $tournament;
		$this->params['stats'] = Stats::getForTournament($tournament);

		$this->view('pages/admin/tournament/tournamentStats');
	}

	public function leagueStats(League $league, ?LeagueCategory $category = null): void {
		$this->params['league'] = $league;
		$this->params['category'] = $category;
		$this->params['stats'] = Stats::getForLeague($league);

		$this->view('pages/admin/tournament/leagueStats');
	}

}