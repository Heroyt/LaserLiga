<?php

namespace App\Controllers;

use App\Models\Tournament\League;
use App\Models\Tournament\LeagueTeam;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controller;
use Lsr\Core\Templating\Latte;
use Lsr\Interfaces\RequestInterface;

class LeagueController extends Controller
{

	public function __construct(
		Latte                 $latte,
		private readonly Auth $auth,
	) {
		parent::__construct($latte);
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params['user'] = $this->auth->getLoggedIn();
	}

	public function detail(League $league): void {
		$this->title = 'Liga %s';
		$this->titleParams[] = $league->name;
		$this->description = 'Liga %s v %s, aneb několik na sebe navazujících turnajů.';
		$this->descriptionParams[] = $league->name;
		$this->descriptionParams[] = $league->arena->name;
		$this->params['league'] = $league;

		$this->view('/pages/league/detail');
	}

	public function teamDetail(LeagueTeam $team): void {
		$this->title = 'Statistiky týmu - %s';
		$this->titleParams[] = $team->name;
		$this->params['currTeam'] = $team;
		$this->view('pages/league/team');
	}

}