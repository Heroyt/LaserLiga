<?php

namespace App\Controllers\User;

use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Templates\User\UserTournamentParameters;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Templating\Latte;
use Lsr\Interfaces\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @property UserTournamentParameters $params
 */
class UserTournamentController extends AbstractUserController
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private readonly Auth $auth,
	) {
		parent::__construct();
		$this->params = new UserTournamentParameters();
	}

	public function init(RequestInterface $request): void {
		parent::init($request);

		$this->params->user = $this->auth->getLoggedIn();
	}

	public function myTournaments(?string $code = null): ResponseInterface {
		if (empty($code)) {
			$player = $this->params->user?->player;
		}
		if (!isset($player)) {
			$player = $this->getUser($code ?? '')->player;
		}
		/** @var LigaPlayer $player */

		$this->params->breadcrumbs = [
			'Laser Liga'          => [],
			$player->nickname     => ['user', $player->getCode()],
			lang('Turnaje hráče') => ['user', $player->getCode(), 'tournaments'],
		];
		$this->title = 'Turnaje hráče - %s';
		$this->titleParams[] = $player->nickname;

		$this->params->currPlayer = $player;
		$this->params->tournaments = $player->getTournaments();
		$this->params->players = $player->getTournamentPlayers();

		return $this->view('pages/tournament/my');
	}

}