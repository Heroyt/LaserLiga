<?php

namespace App\Controllers;

use App\Models\Auth\User;
use App\Models\DataObjects\Game\PlayerGamesGame;
use App\Services\Player\PlayerRankOrderService;
use App\Templates\Player\ProfileParameters;
use DateTimeImmutable;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

/**
 * @property ProfileParameters $params
 */
class Dashboard extends Controller
{

	protected string $title       = 'Dashboard';
	protected string $description = '';

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		protected readonly Auth                 $auth,
		private readonly PlayerRankOrderService $rankOrderService,
	) {
		parent::__construct();
		$this->params = new ProfileParameters();
	}

	public function show(): ResponseInterface {
		$this->params->addCss = ['pages/playerProfile.css'];
		$user = $this->auth->getLoggedIn();
		assert($user !== null, 'User not logged in');
		assert($user->player !== null, 'User is not a player');
		$this->params->loggedInUser = $this->params->user = $user;
		$this->params->lastGames = $user->createOrGetPlayer()
		                                ->queryGames()
		                                ->limit(10)
		                                ->orderBy('start')
		                                ->desc()
		                                ->cacheTags(
			                                'user/games',
			                                'user/' . $user->id . '/games',
			                                'user/' . $user->id . '/lastGames'
		                                )
		                                ->fetchAllDto(PlayerGamesGame::class);
		bdump($this->params);
		$this->title = 'Nástěnka hráče - %s';
		$this->titleParams[] = $user->name;
		$this->params->breadcrumbs = [
			'Laser Liga' => [],
			$user->name  => ['user', $user->player->getCode()],
		];
		$this->description = 'Profil a statistiky všech laser game her hráče %s';
		$this->descriptionParams[] = $user->name;
		$this->params->rankOrder = $this->rankOrderService->getDateRankForPlayer(
			$user->createOrGetPlayer(),
			new DateTimeImmutable()
		);
		return $this->view('pages/dashboard/index');
	}

	public function bp(): ResponseInterface {
		return $this->app->redirect('https://youtu.be/dQw4w9WgXcQ');
	}

}