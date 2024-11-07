<?php

namespace App\Controllers\Admin;

use App\Models\Tournament\League\League;
use App\Models\Tournament\League\LeagueCategory;
use App\Models\Tournament\Stats;
use App\Models\Tournament\Tournament;
use JsonException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Psr\Http\Message\ResponseInterface;

class TournamentStats extends Controller
{

	/**
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function tournamentStats(Tournament $tournament): ResponseInterface {
		$this->params['tournament'] = $tournament;
		$this->params['stats'] = Stats::getForTournament($tournament);

		return $this->view('pages/admin/tournament/tournamentStats');
	}

	/**
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function leagueStats(League $league, ?LeagueCategory $category = null): ResponseInterface {
		$this->params['league'] = $league;
		$this->params['category'] = $category;
		$this->params['stats'] = Stats::getForLeague($league);

		return $this->view('pages/admin/tournament/leagueStats');
	}

}