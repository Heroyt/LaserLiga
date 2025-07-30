<?php
declare(strict_types=1);

namespace App\Controllers\User;

use App\Models\Achievements\Title;
use App\Models\Arena;
use App\Models\Auth\Enums\ConnectionType;
use App\Models\Auth\User;
use App\Models\Auth\UserConnection;
use App\Request\User\UserSettingsRequest;
use App\Services\Achievements\TitleProvider;
use App\Services\Avatar\AvatarService;
use App\Services\Avatar\AvatarType;
use App\Services\UserRegistrationService;
use App\Templates\User\UserSettingsParameters;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\Interfaces\RequestInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Exceptions\ValidationMultiException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
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
		private readonly UserRegistrationService $userRegistrationService,
	) {
		
		$this->params = new UserSettingsParameters();
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params->loggedInUser = $this->auth->getLoggedIn();
	}

	public function show(Request $request): ResponseInterface {
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

		$this->params->tab = $request->getGet('tab', 'settings');

		return $this->view('pages/profile/index');
	}

	public function process(RequestValidationMapper $mapper, Request $request): ResponseInterface {
		if (!empty($request->getErrors())) {
			return $this->respondForm($request, statusCode: 403);
		}

		/** @var User $user */
		$user = $this->auth->getLoggedIn();

		try {
			$data = $mapper->setRequest($request)->mapBodyToObject(UserSettingsRequest::class);
		} catch (ValidationMultiException $e) {
			foreach ($e->exceptions as $error) {
				$request->errors[$error->property] = lang($error->getMessage(), context: 'errors');
			}
			return $this->respondForm($request, statusCode: 400);
		} catch (ValidationException $error) {
			$request->errors[$error->property] = lang($error->getMessage(), context: 'errors');
			return $this->respondForm($request, statusCode: 400);
		}

		$arena = null;

		// Check duplicate emails
		$email = $data->email;
		$test = User::getByEmail($email);
		if (isset($test) && $test->id !== $user->id) {
			$request->passErrors['email'] = lang('E-mail již používá jiný hráč', context: 'errors');
		}

		// Check home arena
		try {
			if (!empty($data->arena)) {
				$arena = Arena::get((int) $data->arena);
			}
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException) {
			$request->passErrors['arena'] = lang('Aréna neexistuje', context: 'errors');
		}

		// Do not allow dates smaller than 5 years ago
		if (($data->birthday !== null) && (int)$data->birthday->format('Y') > (((int)date('Y')) - 5)) {
			$data->birthday = null;
		}

		$player = $user->createOrGetPlayer($arena);

		// Title
		$title = null;
		$titleId = (int) $data->title;
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

		// Avatar
		$type = AvatarType::tryFrom($data->type);
		if ($type === null) {
			// If no type is set, use random type
			$type = AvatarType::getRandom();
		}
		// Default seed value
		if (empty($data->seed)) {
			$data->seed = $player->getCode();
		}
		// Update avatar only if it changed
		if ($type->value !== $player->avatarStyle || $data->seed !== $player->avatarSeed) {
			$player->avatar = $this->avatarService->getAvatar($data->seed, $type);
			$player->avatarStyle = $type->value;
			$player->avatarSeed = $data->seed;
		}

		// Connections - LaserMaxx
		$laserMaxx = $data->mylasermaxx;
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

		// Connections - LaserForce
		$laserForce = $data->laserforce;
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

		// Handle errors
		if (!empty($request->passErrors)) {
			return $this->respondForm($request, statusCode: 400);
		}

		// Update values
		$user->name = $data->name;
		$player->nickname = $data->name;

		$emailChanged = $user->email !== $data->email;
		$user->email = $data->email;
		$player->email = $data->email;
		$player->birthday = $data->birthday;
		if (isset($arena)) {
			$player->arena = $arena;
		}
		if (isset($title)) {
			$player->title = $title;
		}

		if ($emailChanged) {
			$user->isConfirmed = false;
			$user->emailTimestamp = null;
		}

		if (!$user->save() || !$player->save()) {
			$request->addPassError(lang('Profil se nepodařilo uložit'));
			return $this->respondForm($request, statusCode: 500);
		}
		$user->clearCache();
		$player->clearCache();

		if ($emailChanged) {
			$this->userRegistrationService->sendEmailConfirmation($user);
		}

		$request->passNotices[] = [
			'type'    => 'success',
			'content' => lang('Úspěšně uloženo'),
			'title'   => lang('Formulář'),
		];

		$oldPassword = $data->oldPassword;
		$password = $data->password;
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
		$player->clearCache();
		return $this->respond([$player, $type, $avatarType, $seed]);
	}

	public function sendNewConfirmEmail() : ResponseInterface {
		/** @var User $user */
		$user = $this->auth->getLoggedIn();

		if ($user->isConfirmed) {
			return $this->respond(new ErrorResponse(lang('Uživatel je již ověřený'), ErrorType::VALIDATION), 400);
		}

		$this->userRegistrationService->sendEmailConfirmation($user);

		return $this->respond(new SuccessResponse(lang('Potvrzovací e-mail byl odeslaný')));
	}
}