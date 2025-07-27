<?php
declare(strict_types=1);

namespace App\Controllers\User;

use App\Models\Auth\User;
use App\Services\Player\PlayerUserService;
use App\Templates\User\UserFindGamesParameters;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Interfaces\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @property UserFindGamesParameters $params
 */
class UserFindGamesController extends AbstractUserController
{
	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private readonly Auth              $auth,
		private readonly PlayerUserService $userService,
	) {
		
		$this->params = new UserFindGamesParameters();
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$user = $this->auth->getLoggedIn();
		assert($user !== null, 'User is not logged in');
		$this->params->loggedInUser = $user;
	}

	public function show(): ResponseInterface {
		assert($this->params->loggedInUser->player !== null, 'User is not a player');
		$this->params->breadcrumbs = [
			'Laser Liga'                      => [],
			$this->params->loggedInUser->name => ['user', $this->params->loggedInUser->player->getCode()],
			lang('Najít hry')                 => [
				'user',
				$this->params->loggedInUser->player->getCode(),
				'findgames',
			],
		];
		$this->title = 'Najít hry - %s';
		$this->titleParams[] = $this->params->loggedInUser->name;
		$this->description = 'Najít další hry hráče pro přiřazení.';
		$this->params->possibleMatches = $this->userService->scanPossibleMatches($this->params->loggedInUser);
		$this->params->games = [];
		foreach ($this->params->possibleMatches as $match) {
			$this->params->games[] = $match->game;
		}
		return $this->view('pages/profile/findGames');
	}
}