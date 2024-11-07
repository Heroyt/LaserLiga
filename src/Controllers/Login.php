<?php

namespace App\Controllers;

use App\Models\Arena;
use App\Models\Auth\User;
use App\Services\Turnstile;
use App\Templates\Login\LoginParams;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Get;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Core\Templating\Latte;
use Lsr\Interfaces\RequestInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Nette\Security\Passwords;
use Nette\Utils\Validators;
use Psr\Http\Message\ResponseInterface;

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
		protected readonly Auth    $auth,
		private readonly Passwords $passwords,
		private readonly Turnstile $turnstile,
	) {
		parent::__construct();
		$this->params = new LoginParams();
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params->turnstileKey = $this->turnstile->getKey();
	}

	public function show() : ResponseInterface {
		$this->title = 'Přihlášení';
		$this->params->breadcrumbs = [
			'Laser Liga'       => [],
			lang($this->title) => ['login'],
		];
		$this->description = 'Přihlášení do systému Laser ligy.';
		return $this->view('pages/login/index');
	}

	public function processRegister(Request $request) : ResponseInterface {
		$this->title = 'Registrace';
		$this->params->breadcrumbs = [
			'Laser Liga'       => [],
			lang($this->title) => ['register'],
		];
		$this->description = 'Vytvořte si nový hráčský účet v systému Laser liga.';
		// Validate
		$email = (string) ($request->getPost('email', ''));
		$password = (string) ($request->getPost('password', ''));
		$name = (string) ($request->getPost('name', ''));
		$arena = null;

		$this->validateCaptcha($request);

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
		try {
			/** @var numeric|null $arenaId */
			$arenaId = $request->getPost('arena');
			if (!empty($arenaId) && ($arena = Arena::get($arenaId)) === null) {
				$this->params->errors['arena'] = lang('Aréna neexistuje', context: 'errors');
			}
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			$this->params->errors['arena'] = lang('Aréna neexistuje', context: 'errors');
		}
		if (!empty($this->params->errors)) {
			$this->params->arenas = Arena::getAll();
			return $this->view('pages/login/register');
		}

		/** @var User|null $user */
		$user = $this->auth->register($email, $password, $name);
		if (!isset($user)) {
			$this->params->arenas = Arena::getAll();
			$this->params->errors[] = lang('Něco se pokazilo.', context: 'errors');
			return $this->view('pages/login/register');
		}

		try {
			$user->createOrGetPlayer($arena);
		} catch (ValidationException) {
		}

		// Login user
		$this->auth->setLoggedIn($user);
		return $this->app->redirect('dashboard', $request);
	}

	public function register() : ResponseInterface {
		$this->title = 'Registrace';
		$this->params->breadcrumbs = [
			'Laser Liga'       => [],
			lang($this->title) => ['register'],
		];
		$this->description = 'Vytvořte si nový hráčský účet v systému Laser liga.';
		$this->params->arenas = Arena::getAll();
		return $this->view('pages/login/register');
	}

	public function process(Request $request) : ResponseInterface {
		$this->title = 'Přihlášení';
		$this->params->breadcrumbs = [
			'Laser Liga'       => [],
			lang($this->title) => ['login'],
		];
		$this->description = 'Přihlášení do systému Laser ligy.';
		// Validate
		$email = (string) ($request->getPost('email', ''));
		$password = (string) ($request->getPost('password', ''));
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
					'expire'    => new \DateTimeImmutable('+ 30 days'),
				]
			);
			setcookie('rememberme', $token.':'.$validator, time() + (30 * 24 * 3600));
		}

		$request->passNotices[] = ['type' => 'info', 'content' => lang('Přihlášení bylo úspěšné.')];
		return $this->app->redirect('dashboard', $request);
	}

	#[Get('/logout', 'logout'), Post('/logout')]
	public function logout(Request $request) : ResponseInterface {
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
		return $this->app->redirect('login', $request);
	}

}