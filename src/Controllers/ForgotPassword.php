<?php

namespace App\Controllers;

use App\Mails\Message;
use App\Models\Auth\User;
use App\Services\MailService;
use DateTimeImmutable;
use Dibi\DateTime;
use Dibi\Exception;
use Lsr\Core\App;
use Lsr\Core\Controller;
use Lsr\Core\DB;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Logging\Logger;
use Nette\Mail\SendException;
use Nette\Utils\Validators;

class ForgotPassword extends Controller
{

	public function __construct(
		Latte                        $latte,
		private readonly MailService $mailService
	) {
		parent::__construct($latte);
	}

	public function forgot(Request $request) : void {
		$email = (string) $request->getPost('email', '');
		if (!empty($email) && empty($request->getErrors())) {
			$logger = new Logger(LOG_DIR, 'passReset');
			if (Validators::isEmail($email)) {
				if (User::existsByEmail($email)) {
					/** @var User $user */
					$user = User::getByEmail($email);
					// Generate hash
					$token = md5(time().$email.bin2hex(random_bytes(32)));
					$hash = hash_hmac('sha256', $email, $token);

					$message = new Message('mails/forgotPassword/mail');
					$message->setFrom('app@laserliga.cz', 'LaserLiga');
					$message->setUser($user);
					$message->setSubject(lang('[LaserLiga] Obnova hesla'));
					$message->params['hash'] = $hash;

					try {
						DB::update(
							User::TABLE,
							['forgot_token' => $token, 'forgot_timestamp' => new DateTimeImmutable()],
							['[id_user] = %i', $user->id]
						);

						$this->mailService->send($message);
						$logger->info('Sent new password reset request: '.$email);
					} catch (SendException $e) {
						$this->params['errors'][] = lang('Nepodařilo se odeslat e-mail pro obnovu hesla.', context: 'errors');
						$logger->exception($e);
					} catch (Exception $e) {
						$this->params['errors'][] = lang('Nepodařilo se vygenerovat token pro obnovu hesla.', context: 'errors');
						$logger->exception($e);
					}
				}
				if (empty($this->params['errors'])) {
					$this->params['notices'][] = [
						'type'    => 'success',
						'content' => lang('Pokud uživatel existuje, odeslali jsme vám e-mail s odkazem obnovu hesla.'),
					];
					$this->params['notices'][] = [
						'type'    => 'info',
						'content' => lang('LaserLiga ještě není připravená na 100%, proto se může stát, že email spadne do spamu.'),
					];
				}
			}
			else {
				$this->params['errors']['email'] = lang('E-mail není platný', context: 'errors');
			}
		}
		$this->view('pages/login/forgot');
	}

	public function reset(Request $request) : void {
		$hash = (string) $request->getGet('token', '');
		$email = (string) $request->getGet('email', '');

		if (empty($hash) || empty($email)) {
			$this->resetInvalid('Požadavek neexistuje');
			return;
		}

		$this->params['hash'] = $hash;
		$this->params['email'] = $email;

		// Validate hash and email
		if (!User::existsByEmail($email)) {
			$this->resetInvalid('Neplatný požadavek');
			return;
		}
		$row = DB::select(User::TABLE, '[forgot_token], [forgot_timestamp]')
						 ->where('[email] = %s', $email)
						 ->fetch(false);
		if (!isset($row)) {
			$this->resetInvalid('Neplatný požadavek');
			return;
		}
		/** @var DateTime|null $timestamp */
		$timestamp = $row->forgot_timestamp;
		if (!isset($timestamp) || $timestamp < (new DateTimeImmutable('- 6 hours'))) {
			$this->resetInvalid('Požadavek vypršel');
			return;
		}
		if (!hash_equals($hash, hash_hmac('sha256', $email, $row->forgot_token))) {
			$this->resetInvalid('Neplatný požadavek', 403);
			return;
		}

		$password = (string) $request->getPost('password', '');
		$password1 = (string) $request->getPost('password1', '');
		if (!empty($password) && empty($request->getErrors())) {
			$logger = new Logger(LOG_DIR, 'passReset');
			if ($password === $password1) {
				/** @var User $user */
				$user = User::getByEmail($email);
				$user->setPassword($password);
				if ($user->save()) {
					try {
						DB::update(
							User::TABLE,
							['forgot_token' => null, 'forgot_timestamp' => null],
							['[id_user] = %i', $user->id],
						);
					} catch (Exception $e) {
						$logger->exception($e);
					}
					$logger->info('Changed password for user: '.$user->email);
					$request->passNotices[] = ['type' => 'success', 'content' => lang('Heslo bylo úspěšně změněno')];
					App::redirect('login', $request);
				}
			}
			else {
				$this->params['errors'][] = lang('Hesla nejsou stejná', context: 'errors');
			}
		}
		$this->view('pages/login/reset');
	}

	private function resetInvalid(string $message, int $code = 400) : void {
		http_response_code($code);
		$this->params['errors'][] = lang($message, context: 'errors');
		$this->view('pages/login/resetInvalid');
	}
}