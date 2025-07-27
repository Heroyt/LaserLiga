<?php
declare(strict_types=1);

namespace App\Controllers\User;

use App\Models\Auth\User;
use DateTimeImmutable;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;

class UserPrivacyController extends AbstractUserController
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		protected readonly Auth $auth,
	) {
		
	}

	public function agree(Request $request): ResponseInterface {
		$loggedInUser = $this->auth->getLoggedIn();
		if ($loggedInUser === null) {
			$request->passErrors[] = lang('Musíte být přihlášen', context: 'error');
			return $this->app->redirect(['login'], $request);
		}

		$loggedInUser->privacyVersion = User::CURRENT_PRIVACY_VERSION;
		$loggedInUser->privacyConfirmed = new DateTimeImmutable();
		if (!$loggedInUser->save()) {
			$request->passErrors[] = lang('Něco se nepodařilo', context: 'error');
			return $this->app->redirect(['dashboard'], $request);
		}
		$loggedInUser->clearCache();
		$request->passNotices[] = [
			'type'    => 'success',
			'title'   => lang('Souhlas byl uložen. Děkujeme!', context: 'success'),
			'content' => '',
		];
		return $this->app->redirect(['dashboard'], $request);
	}

}