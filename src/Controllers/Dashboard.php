<?php

namespace App\Controllers;

use App\Models\Auth\User;
use App\Services\Player\PlayerRankOrderService;
use DateTimeImmutable;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controller;
use Lsr\Core\Templating\Latte;

class Dashboard extends Controller
{

	protected string $title = 'Dashboard';
	protected string $description = '';

	/**
	 * @param Latte $latte
	 * @param Auth<User> $auth
	 */
	public function __construct(
		protected Latte                         $latte,
		protected readonly Auth                 $auth,
		private readonly PlayerRankOrderService $rankOrderService,
	) {
		parent::__construct($latte);
	}

	public function show(): void {
		$this->params['addCss'] = ['pages/playerProfile.css'];
		$this->params['loggedInUser'] = $this->params['user'] = $this->auth->getLoggedIn();
		$this->params['lastGames'] = $this->params['user']->player->queryGames()
																															->limit(10)
																															->orderBy('start')
																															->desc()
																															->cacheTags('user/games', 'user/' . $this->params['user']->id . '/games', 'user/' . $this->params['user']->id . '/lastGames')
																															->fetchAll();
		$this->title = 'Nástěnka hráče - %s';
		$this->titleParams[] = $this->params['user']->name;
		$this->params['breadcrumbs'] = [
			'Laser Liga'                => [],
			$this->params['user']->name => ['user', $this->params['user']->player->getCode()],
		];
		$this->description = 'Profil a statistiky všech laser game her hráče %s';
		$this->descriptionParams[] = $this->params['user']->name;
		$this->params['rankOrder'] = $this->rankOrderService->getDateRankForPlayer($this->params['user']->createOrGetPlayer(), new DateTimeImmutable());
		$this->view('pages/dashboard/index');
	}

	public function bp(): never {
		header('location: https://youtu.be/dQw4w9WgXcQ');
		exit;
	}

}