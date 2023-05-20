<?php

namespace App\Controllers\User;

use App\Models\Auth\LigaPlayer;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Templating\Latte;
use Lsr\Interfaces\RequestInterface;

class UserTournamentController extends AbstractUserController
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

	public function myTournaments(?string $code = null): void {
		if (!isset($code)) {
			$player = $this->params['user']?->player;
		}
		if (!isset($player)) {
			$player = $this->getUser($code ?? '')->player;
		}
		/** @var LigaPlayer $player */

		$this->title = 'Turnaje hráče - %s';
		$this->titleParams[] = $player->nickname;

		$this->params['currPlayer'] = $player;
		$this->params['tournaments'] = $player->getTournaments();
		$this->params['players'] = $player->getTournamentPlayers();

		$this->view('pages/tournament/my');
	}

}