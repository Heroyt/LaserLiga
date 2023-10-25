<?php

namespace App\Controllers;

use App\Models\Tournament\League;
use App\Models\Tournament\LeagueTeam;
use App\Models\Tournament\Stats;
use Lsr\Core\App;
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

	public function show(): void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'            => [],
			lang('Ligy laser game') => ['league'],
		];
		$this->title = 'Ligy laser game';
		$this->description = 'Organizované laser game ligy - skupiny turnajů, které na sebe navazují.';
		$this->params['leagues'] = League::getAll();

		$this->view('pages/league/index');
	}

	public function detail(League $league): void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'         => [],
			$league->arena->name => ['arena', $league->arena->id],
			lang('Turnaje')      => App::getLink(['arena', $league->arena->id]) . '#tournaments-tab',
			$league->name        => ['league', $league->id],
		];
		$this->title = 'Liga %s';
		$this->titleParams[] = $league->name;
		$this->description = 'Liga %s v %s, aneb několik na sebe navazujících turnajů.';
		$this->descriptionParams[] = $league->name;
		$this->descriptionParams[] = $league->arena->name;
		$this->params['league'] = $league;
		$this->params['stats'] = Stats::getForLeague($league, true);

		$this->view('/pages/league/detail');
	}

	public function teamDetail(LeagueTeam $team): void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'               => [],
			$team->league->arena->name => ['arena', $team->league->arena->id],
			lang('Turnaje')            => App::getLink(['arena', $team->league->arena->id]) . '#tournaments-tab',
			$team->league->name        => ['league', $team->league->id],
			$team->name                => ['league', 'team', $team->id],
		];
		$this->title = 'Statistiky týmu - %s';
		$this->titleParams[] = $team->name;
		$this->description = 'Statistiky týmu %s hrající v lize %s.';
		$this->descriptionParams[] = $team->name;
		$this->descriptionParams[] = $team->league->name;
		$this->params['currTeam'] = $team;
		$this->view('pages/league/team');
	}

}