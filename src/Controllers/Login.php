<?php

namespace App\Controllers;

use App\Models\Arena;
use App\Models\Auth\User;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controller;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Get;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Core\Templating\Latte;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Nette\Security\Passwords;
use Nette\Utils\Validators;

class Login extends Controller
{

	/**
	 * @var array{errors: array<string|int, string>,arenas?:Arena[]}
	 */
	public array $params = ['errors' => []];

	/**
	 * @param Latte      $latte
	 * @param Auth<User> $auth
	 */
	public function __construct(
		protected Latte            $latte,
		protected readonly Auth    $auth,
		private readonly Passwords $passwords,
	) {
		parent::__construct($latte);
	}

	public function show() : void {
		$this->view('pages/login/index');
	}

	public function processRegister(Request $request) : void {
		// Validate
		$email = (string) ($request->post['email'] ?? '');
		$password = (string) ($request->post['password'] ?? '');
		$name = (string) ($request->post['name'] ?? '');
		$arena = null;

		if (empty($email)) {
			$this->params['errors']['email'] = lang('E-mail je povinný', context: 'errors');
		}
		else if (!Validators::isEmail($email)) {
			$this->params['errors']['email'] = lang('E-mail není validní', context: 'errors');
		}
		else if (User::existsByEmail($email)) {
			$this->params['errors']['email'] = lang('Uživatel s tímto e-mailem již existuje', context: 'errors');
		}
		if (empty($password)) {
			$this->params['errors']['password'] = lang('Heslo je povinné', context: 'errors');
		}
		if (empty($name)) {
			$this->params['errors']['name'] = lang('Jméno je povinné', context: 'errors');
		}
		try {
			if (!empty($request->post['arena']) && ($arena = Arena::get($request->post['arena'])) === null) {
				$this->params['errors']['arena'] = lang('Aréna neexistuje', context: 'errors');
			}
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			$this->params['errors']['arena'] = lang('Aréna neexistuje', context: 'errors');
		}
		if (!empty($this->params['errors'])) {
			$this->params['arenas'] = Arena::getAll();
			$this->view('pages/login/register');
			return;
		}

		/** @var User|null $user */
		$user = $this->auth->register($email, $password, $name);
		if (!isset($user)) {
			$this->params['arenas'] = Arena::getAll();
			$this->params['errors'][] = lang('Něco se pokazilo.', context: 'errors');
			$this->view('pages/login/register');
			return;
		}

		try {
			$user->createOrGetPlayer($arena);
		} catch (ValidationException $e) {
		}

		// Login user
		$this->auth->setLoggedIn($user);
		App::redirect('dashboard', $request);
	}

	public function register() : void {
		$this->params['arenas'] = Arena::getAll();
		$this->view('pages/login/register');
	}

	public function process(Request $request) : void {
		// Validate
		$email = (string) ($request->post['email'] ?? '');
		$password = (string) ($request->post['password'] ?? '');
		$rememberMe = !empty($request->post['remember']);

		if (empty($email)) {
			$this->params['errors']['email'] = lang('E-mail je povinný', context: 'errors');
		}
		else if (!Validators::isEmail($email)) {
			$this->params['errors']['email'] = lang('E-mail není validní', context: 'errors');
		}
		if (empty($password)) {
			$this->params['errors']['password'] = lang('Heslo je povinné', context: 'errors');
		}
		if (!empty($this->params['errors'])) {
			$this->view('pages/login/index');
			return;
		}

		if (!$this->auth->login($email, $password, $rememberMe)) {
			$this->params['errors']['login'] = lang('E-mail nebo heslo není správné.', context: 'errors');
			$this->view('pages/login/index');
			return;
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
		App::redirect('dashboard', $request);
	}

	#[Get('/logout', 'logout'), Post('/logout')]
	public function logout(Request $request) : void {
		if ($this->auth->loggedIn()) {
			$this->auth->logout();
		}
		if (isset($_COOKIE['rememberme'])) {
			$ex = explode(':', $_COOKIE['rememberme']);
			if (count($ex) === 2) {
				[$token, $validator] = $ex;
				DB::delete('user_tokens', ['[token] = %s', $token]);
			}
			setcookie('rememberme', '', -1);
		}
		$request->addPassNotice(lang('Odhlášení bylo úspěšné.'));
		App::redirect('login', $request);
	}

}