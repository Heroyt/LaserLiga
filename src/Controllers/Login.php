<?php

namespace App\Controllers;

use App\Exceptions\UserRegistrationException;
use App\Models\Arena;
use App\Models\Auth\User;
use App\Models\DataObjects\User\ForgotData;
use App\Services\Turnstile;
use App\Services\UserRegistrationService;
use App\Templates\Login\LoginParams;
use DateTimeImmutable;
use Dibi\Exception;
use Lsr\Core\Auth\Exceptions\DuplicateEmailException;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Get;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Core\Session;
use Lsr\Db\DB;
use Lsr\Interfaces\RequestInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Nette\Security\Passwords;
use Nette\Utils\Validators;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;
use Tracy\Debugger;

/**
 * @property LoginParams $params
 */
class Login extends Controller
{
	use CaptchaValidation;

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		protected readonly Auth                  $auth,
		private readonly Passwords               $passwords,
		private readonly Turnstile               $turnstile,
		private readonly UserRegistrationService $userRegistration,
		private readonly Session $session,
	) {
		parent::__construct();
		$this->params = new LoginParams();
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params->turnstileKey = $this->turnstile->getKey();
	}

	public function show(): ResponseInterface {
		$this->title = 'Přihlášení';
		$this->params->breadcrumbs = [
			'Laser Liga'       => [],
			lang($this->title) => ['login'],
		];
		$this->description = 'Přihlášení do systému Laser ligy.';
		return $this->view('pages/login/index');
	}

	public function processRegister(Request $request): ResponseInterface {
		$this->title = 'Registrace';
		$this->params->breadcrumbs = [
			'Laser Liga'       => [],
			lang($this->title) => ['register'],
		];
		$this->description = 'Vytvořte si nový hráčský účet v systému Laser liga.';

		if (!formValid('register-user')) {
			$this->params->errors[] = lang('Požadavek vypršel, zkuste znovu načíst stránku.', context: 'errors');
			$this->params->arenas = Arena::getAll();
			return $this->view('pages/login/register');
		}

		if (!$this->validateCaptcha($request)) {
			$this->params->arenas = Arena::getAll();
			return $this->view('pages/login/register');
		}

		$botTest = $request->getPost('password_confirmation', '');

		if (!empty($botTest)) {
			$this->getApp()->getLogger()->notice(
				'Detected bot registration',
				[
					'path'   => $request->getPath(),
					'body'   => $request->getParsedBody(),
					'ip'     => $request->getIp(),
					'cookie' => $request->getCookieParams(),
					'errors' => $this->params->errors,
				]
			);
			return $this->respond(new SuccessResponse()); // Fake response
		}

		// Validate
		/** @var string $email */
		$email = $request->getPost('email', '');
		/** @var string $password */
		$password = $request->getPost('password', '');
		/** @var string $name */
		$name = $request->getPost('name', '');
		$privacy = !empty($request->getPost('privacy_policy', ''));
		$arena = null;

		if (empty($email)) {
			$this->params->errors['email'] = lang('E-mail je povinný', context: 'errors');
		}
		else if (!Validators::isEmail($email)) {
			$this->params->errors['email'] = lang('E-mail není validní', context: 'errors');
		}
		else if (User::existsByEmail($email)) {
			$this->params->errors['email'] = lang('Uživatel s tímto e-mailem již existuje', context: 'errors');
		}
		if (empty($password)) {
			$this->params->errors['password'] = lang('Heslo je povinné', context: 'errors');
		}
		if (empty($name)) {
			$this->params->errors['name'] = lang('Jméno je povinné', context: 'errors');
		}
		else if ($this->containsUrl($name)) {
			$this->params->errors['name'] = lang('Jméno nesmí obsahovat URL', context: 'errors');
		}
		if (!$privacy) {
			$this->params->errors['privacy_policy'] = lang('Musíte souhlasit s podmínkami', context: 'errors');
		}
		try {
			/** @var numeric|null $arenaId */
			$arenaId = $request->getPost('arena');
			if (!empty($arenaId)) {
				$arena = Arena::get((int)$arenaId);
			}
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException) {
			$this->params->errors['arena'] = lang('Aréna neexistuje', context: 'errors');
		}
		if (!empty($this->params->errors)) {
			$this->params->arenas = Arena::getAll();
			return $this->view('pages/login/register');
		}

		try {
			$user = $this->userRegistration->registerUser($name, $email, $password, $arena, $privacy);
		} catch (UserRegistrationException|Exception|RandomException $e) {
			$this->params->arenas = Arena::getAll();
			$this->params->errors[] = lang('Něco se pokazilo.', context: 'errors');
			Debugger::log($e);
			return $this->view('pages/login/register');
		} catch (DuplicateEmailException $e) {
			$this->params->arenas = Arena::getAll();
			$this->params->errors[] = lang('Uživatel s tímto e-mailem již existuje.', context: 'errors');
			Debugger::log($e);
			return $this->view('pages/login/register');
		}

		// Login user
		$this->auth->setLoggedIn($user);
		return $this->app->redirect('dashboard', $request);
	}

	public function register(): ResponseInterface {
		$this->title = 'Registrace';
		$this->params->breadcrumbs = [
			'Laser Liga'       => [],
			lang($this->title) => ['register'],
		];
		$this->description = 'Vytvořte si nový hráčský účet v systému Laser liga.';
		$this->params->arenas = Arena::getAll();
		return $this->view('pages/login/register');
	}

	public function process(Request $request): ResponseInterface {
		$this->title = 'Přihlášení';
		$this->params->breadcrumbs = [
			'Laser Liga'       => [],
			lang($this->title) => ['login'],
		];
		$this->description = 'Přihlášení do systému Laser ligy.';
		// Validate
		/** @var string $email */
		$email = $request->getPost('email', '');
		/** @var string $password */
		$password = $request->getPost('password', '');
		$rememberMe = !empty($request->getPost('remember'));

		$this->validateCaptcha($request);

		if (empty($email)) {
			$this->params->errors['email'] = lang('E-mail je povinný', context: 'errors');
		}
		else if (!Validators::isEmail($email)) {
			$this->params->errors['email'] = lang('E-mail není validní', context: 'errors');
		}
		if (empty($password)) {
			$this->params->errors['password'] = lang('Heslo je povinné', context: 'errors');
		}
		if (!empty($this->params->errors)) {
			return $this->view('pages/login/index');
		}

		if (!$this->auth->login($email, $password, $rememberMe)) {
			$this->params->errors['login'] = lang('E-mail nebo heslo není správné.', context: 'errors');
			return $this->view('pages/login/index');
		}
		if ($rememberMe) {
			$token = bin2hex(random_bytes(16));
			$validator = bin2hex(random_bytes(32));
			DB::insert(
				'user_tokens',
				[
					'token'     => $token,
					'validator' => $this->passwords->hash($validator),
					'id_user'   => $this->auth->getLoggedIn()->id,
					'expire'    => new DateTimeImmutable('+ 30 days'),
				]
			);
			setcookie('rememberme', $token . ':' . $validator, time() + (30 * 24 * 3600));
		}

		$request->passNotices[] = ['type' => 'info', 'content' => lang('Přihlášení bylo úspěšné.')];
		return $this->app->redirect('dashboard', $request);
	}

	#[Get('/logout', 'logout'), Post('/logout')]
	public function logout(Request $request): ResponseInterface {
		if ($this->auth->loggedIn()) {
			$this->auth->logout();
		}
		$cookies = $request->getCookieParams();
		if (isset($cookies['rememberme'])) {
			$ex = explode(':', $cookies['rememberme']);
			if (count($ex) === 2) {
				[$token, $validator] = $ex;
				DB::delete('user_tokens', ['[token] = %s', $token]);
			}
			setcookie('rememberme', '', -1);
		}

		$request->addPassNotice(lang('Odhlášení bylo úspěšné.'));

		$kiosk = $this->session->get('kiosk');
		if ($kiosk) {
			$arenaId = $this->session->get('kioskArena');
			return $this->app->redirect(['kiosk', $arenaId], $request);
		}

		return $this->app->redirect('login', $request);
	}

	public function confirm(Request $request): ResponseInterface {
		$this->title = 'Portvdit e-mail';
		$this->description = 'Potvrzení e-mailu registrovaného hráče.';
		$this->params['breadcrumbs'] = [
			'Laser Liga'       => [],
			lang('Přihlášení') => ['login'],
			lang($this->title) => ['login', 'confirm'],
		];

		/** @var string $hash */
		$hash = $request->getGet('token', '');
		/** @var string $email */
		$email = $request->getGet('email', '');

		if (empty($hash) || empty($email)) {
			return $this->confirmInvalid('Požadavek neexistuje');
		}

		// Validate hash and email
		$user = User::getByEmail($email);
		if ($user === null) {
			return $this->confirmInvalid('Uživatel neexistuje');
		}
		$row = DB::select(User::TABLE, '[email_token] as [token], [email_timestamp] as [timestamp]')
		         ->where('[email] = %s', $email)
		         ->fetchDto(ForgotData::class, false);
		if (!isset($row)) {
			return $this->confirmInvalid('Neplatný požadavek');
		}
		if ($row->token === null || !hash_equals($hash, hash_hmac('sha256', $email, $row->token))) {
			return $this->confirmInvalid('Neplatný požadavek', 403);
		}

		$user->emailToken = null;
		$user->emailTimestamp = new DateTimeImmutable();
		$user->isConfirmed = true;
		$user->save();

		return $this->view('pages/login/confirm');
	}

	private function confirmInvalid(string $message, int $code = 400): ResponseInterface {
		$this->title = 'Portvdit e-mail - Neplatný požadavek';
		$this->description = 'Neplatný požadavek pro potvrzení e-mailu.';

		$this->params->errors[] = lang($message, context: 'errors');
		return $this->view('pages/login/confirmInvalid')
		            ->withStatus($code);
	}

	private function containsUrl(string $string) : bool {
		return preg_match('/\b(?:https?|ftp|www)(:\/\/)*[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i', $string) === 1;
	}

}