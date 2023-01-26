<?php

namespace App\Controllers\User;

use App\Models\Arena;
use App\Models\Auth\User;
use JsonException;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Nette\Security\Passwords;

class UserController extends Controller
{

	public function __construct(
		protected Latte              $latte,
		protected readonly Auth      $auth,
		protected readonly Passwords $passwords,
	) {
		parent::__construct($latte);
	}

	public function show() : void {
		$this->params['user'] = $this->auth->getLoggedIn();
		$this->params['arenas'] = Arena::getAll();

		$this->view('pages/profile/index');
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 * @throws ValidationException
	 */
	public function processProfile(Request $request) : never {
		if (!empty($request->getErrors())) {
			$this->respondForm($request, statusCode: 403);
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
			/** @phpstan-ignore-next-line */
			$arenaId = (int) $request->getPost('arena', 0);
			if (!empty($arenaId)) {
				$arena = Arena::get($arenaId);
			}
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			$request->passErrors['arena'] = lang('Aréna neexistuje', context: 'errors');
		}

		if (!empty($request->passErrors)) {
			$this->respondForm($request, statusCode: 400);
		}
		$player = $user->createOrGetPlayer($arena);

		$user->name = $name;
		$player->nickname = $name;
		if (isset($arena)) {
			$player->arena = $arena;
		}

		if (!$user->save()) {
			$request->addPassError(lang('Profil se nepodařilo uložit'));
			$this->respondForm($request, statusCode: 500);
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
				$this->respondForm($request, statusCode: 400);
			}
			$user->password = $this->passwords->hash($password);
			if (!$user->save()) {
				$request->addPassError(lang('Heslo se nepodařilo změnit'));
				$this->respondForm($request, statusCode: 500);
			}
			$request->passNotices[] = [
				'title'   => lang('Formulář'),
				'content' => lang('Heslo bylo změněno'),
			];
		}

		$this->respondForm($request, ['status' => 'ok']);
	}

	/**
	 * @param Request $request
	 * @param array   $data
	 * @param int     $statusCode
	 *
	 * @return never
	 * @throws JsonException
	 */
	public function respondForm(Request $request, array $data = [], int $statusCode = 200) : never {
		if ($request->isAjax()) {
			$data['errors'] += $request->getErrors();
			$data['errors'] += $request->getPassErrors();
			$data['notices'] += $request->getNotices();
			$data['notices'] += $request->getPassNotices();
			$this->respond($data, $statusCode);
		}
		$request->passErrors = array_merge($request->errors, $request->passErrors);
		$request->passNotices = array_merge($request->notices, $request->passNotices);
		App::redirect($request->path, $request);
	}

	public function public(User $user, Request $request) : void {
		$this->view('pages/profile/public');
	}

}