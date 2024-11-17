<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UserRegistrationException;
use App\Mails\Message;
use App\Models\Arena;
use App\Models\Auth\User;
use Dibi\Exception;
use Lsr\Core\Auth\Exceptions\DuplicateEmailException;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Random\RandomException;

readonly class UserRegistrationService
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private Auth $auth,
		private MailService $mailer,
	){}

	/**
	 * @param string     $name
	 * @param string     $email
	 * @param string     $password
	 * @param Arena|null $arena
	 *
	 * @return User
	 * @throws Exception
	 * @throws RandomException
	 * @throws UserRegistrationException
	 * @throws DuplicateEmailException
	 */
	public function registerUser(string $name, string $email, string $password, ?Arena $arena) : User {
		$user = $this->auth->register($email, $password, $name);
		if (!isset($user)) {
			throw new UserRegistrationException();
		}

		try {
			$user->createOrGetPlayer($arena);
		} catch (ValidationException) {
		}

		$this->sendEmailConfirmation($user);

		return $user;
	}

	/**
	 * @param User $user
	 *
	 * @return void
	 * @throws Exception
	 * @throws RandomException
	 */
	public function sendEmailConfirmation(User $user) : void {
		$email = $user->email;

		// Generate hash
		$token = md5(time().$email.bin2hex(random_bytes(32)));
		$hash = hash_hmac('sha256', $email, $token);

		$message = new Message('mails/confirmEmail/mail');
		$message->setFrom('app@laserliga.cz', 'LaserLiga');
		$message->setUser($user);
		$message->setSubject('[LaserLiga] '.lang('Potvrzení e-mailu'));
		$message->params['hash'] = $hash;

		DB::update(
			$user::TABLE,
			['email_token' => $token, 'email_timestamp' => null,],
			['id_user = %i', $user->id]
		);
		$this->mailer->send($message);
	}
}