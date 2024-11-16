<?php
declare(strict_types=1);

namespace App\Controllers\User;

use App\Models\Achievements\Title;
use App\Models\Arena;
use App\Models\Auth\Enums\ConnectionType;
use App\Models\Auth\User;
use App\Models\Auth\UserConnection;
use App\Services\Achievements\TitleProvider;
use App\Services\Avatar\AvatarService;
use App\Services\Avatar\AvatarType;
use App\Templates\User\UserSettingsParameters;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Interfaces\RequestInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Nette\Security\Passwords;
use Psr\Http\Message\ResponseInterface;

/**
 * @property UserSettingsParameters $params
 */
class UserSettingsController extends AbstractUserController
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		protected readonly Auth        $auth,
		protected readonly Passwords   $passwords,
		private readonly TitleProvider $titleProvider,
		private readonly AvatarService               $avatarService,
	) {
		parent::__construct();
		$this->params = new UserSettingsParameters();
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params->loggedInUser = $this->auth->getLoggedIn();
	}

	public function show(): ResponseInterface {
		$this->params->addCss = ['pages/playerSettings.css'];
		/** @var User $user */
		$user = $this->auth->getLoggedIn();
		assert($user->player !== null, 'User is not a player');
		$this->params->user = $user;
		$this->params->arenas = Arena::getAll();
		$this->params->breadcrumbs = [
			'Laser Liga'              => [],
			$user->name               => ['user', $user->player->getCode()],
			lang('Nastavení profilu') => ['user'],
		];

		$this->title = 'Nastavení profilu hráče - %s';
		$this->titleParams[] = $user->name;
		$this->description = 'Nastavení osobních údajů a profilu hráče laser game - %s.';
		$this->descriptionParams[] = $user->name;
		$this->params->titles = $this->titleProvider->getForUser($user->player);

		return $this->view('pages/profile/index');
	}

	public function process(Request $request): ResponseInterface {
		if (!empty($request->getErrors())) {
			return $this->respondForm($request, statusCode: 403);
		}

		/** @var User $user */
		$user = $this->auth->getLoggedIn();

		/** @var string $name */
		$name = $request->getPost('name', '');
		$arena = null;

		if (empty($name)) {
			$request->passErrors['name'] = lang('Jméno je povinné', context: 'errors');
		}
		try {
			$arenaId = (int)$request->getPost('arena', 0);
			if (!empty($arenaId)) {
				$arena = Arena::get($arenaId);
			}
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException) {
			$request->passErrors['arena'] = lang('Aréna neexistuje', context: 'errors');
		}

		$player = $user->createOrGetPlayer($arena);

		$title = null;
		$titleId = (int)$request->getPost('title', 0);
		if ($titleId > 0) {
			try {
				$title = Title::get($titleId);
				if (!in_array($title, $this->titleProvider->getForUser($player), true)) {
					$request->passErrors['title'] = lang('Titul není odemčený', context: 'errors');
				}
			} catch (ModelNotFoundException|ValidationException|DirectoryCreationException) {
				$request->passErrors['title'] = lang('Titul neexistuje', context: 'errors');
			}
		}

		/** @var string|null $laserMaxx */
		$laserMaxx = $request->getPost('mylasermaxx');
		$laserMaxxConnection = $user->getConnectionByType(ConnectionType::MY_LASERMAXX);
		if (empty($laserMaxx) && isset($laserMaxxConnection)) {
			$user->removeConnection($laserMaxxConnection);
		}
		else if (!empty($laserMaxx)) {
			if (!isset($laserMaxxConnection)) {
				$laserMaxxConnection = new UserConnection();
				$laserMaxxConnection->user = $user;
				$laserMaxxConnection->type = ConnectionType::MY_LASERMAXX;
				$user->addConnection($laserMaxxConnection);
			}
			$laserMaxxConnection->identifier = $laserMaxx;
		}

		/** @var string|null $laserForce */
		$laserForce = $request->getPost('laserforce');
		$laserForceConnection = $user->getConnectionByType(ConnectionType::LASER_FORCE);
		if (empty($laserForce) && isset($laserForceConnection)) {
			$user->removeConnection($laserForceConnection);
		}
		else if (!empty($laserForce)) {
			if (!isset($laserForceConnection)) {
				$laserForceConnection = new UserConnection();
				$laserForceConnection->user = $user;
				$laserForceConnection->type = ConnectionType::LASER_FORCE;
				$user->addConnection($laserForceConnection);
			}
			$laserForceConnection->identifier = $laserForce;
		}

		if (!empty($request->passErrors)) {
			return $this->respondForm($request, statusCode: 400);
		}

		$user->name = $name;
		$player->nickname = $name;
		if (isset($arena)) {
			$player->arena = $arena;
		}
		if (isset($title)) {
			$player->title = $title;
		}

		if (!$user->save()) {
			$request->addPassError(lang('Profil se nepodařilo uložit'));
			return $this->respondForm($request, statusCode: 500);
		}
		$request->passNotices[] = [
			'type'    => 'success',
			'content' => lang('Úspěšně uloženo'),
			'title'   => lang('Formulář'),
		];

		/** @var string $oldPassword */
		$oldPassword = $request->getPost('oldPassword', '');
		/** @var string $password */
		$password = $request->getPost('password', '');
		if (!empty($password) && !empty($oldPassword) && !$request->isAjax()) {
			if (!$this->auth->login($user->email, $oldPassword)) {
				$request->passErrors['oldPassword'] = lang('Aktuální heslo není správné');
				return $this->respondForm($request, statusCode: 400);
			}
			$user->password = $this->passwords->hash($password);
			if (!$user->save()) {
				$request->addPassError(lang('Heslo se nepodařilo změnit'));
				return $this->respondForm($request, statusCode: 500);
			}
			$request->passNotices[] = [
				'title'   => lang('Formulář'),
				'content' => lang('Heslo bylo změněno'),
			];
		}

		return $this->respondForm($request, ['status' => 'ok']);
	}

	/**
	 * @param Request             $request
	 * @param array<string,mixed> $data
	 * @param int                 $statusCode
	 *
	 * @return ResponseInterface
	 */
	public function respondForm(Request $request, array $data = [], int $statusCode = 200): ResponseInterface {
		if ($request->isAjax()) {
			$data['errors'] += $request->getErrors();
			$data['errors'] += $request->getPassErrors();
			$data['notices'] += $request->getNotices();
			$data['notices'] += $request->getPassNotices();
			return $this->respond($data, $statusCode);
		}
		$request->passErrors = array_merge($request->errors, $request->passErrors);
		$request->passNotices = array_merge($request->notices, $request->passNotices);
		return $this->app->redirect($request->getPath(), $request);
	}

	public function updateAvatar(string $code, Request $request): ResponseInterface {
		$user = $this->getUser($code);
		$player = $user->createOrGetPlayer();

		$type = (string) $request->getPost('type', ''); // @phpstan-ignore-line
		$avatarType = null;
		if (!empty($type)) {
			$avatarType = AvatarType::tryFrom($type);
		}
		if (!isset($avatarType)) {
			$avatarType = AvatarType::getRandom();
		}
		$seed = (string) $request->getPost('seed', $player->getCode()); // @phpstan-ignore-line
		$player->avatar = $this->avatarService->getAvatar($seed, $avatarType);
		$player->avatarStyle = $avatarType->value;
		$player->avatarSeed = $seed;
		$player->save();
		return $this->respond([$player, $type, $avatarType, $seed]);
	}
}