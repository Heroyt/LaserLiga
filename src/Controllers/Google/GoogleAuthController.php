<?php
declare(strict_types=1);

namespace App\Controllers\Google;


use App\Models\Arena;
use App\Services\Google\GoogleClientFactory;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Logging\Logger;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;

class GoogleAuthController extends Controller
{

	public function __construct(
		private readonly Auth                $auth,
		private readonly GoogleClientFactory $clientFactory,
	) {
	}

	public function start(Arena $arena, Request $request): ResponseInterface {
		$logger = new Logger(LOG_DIR, 'google-oauth');
		$logger->info('New oauth flow started for arena '.$arena->id);

		$referer = $request->getHeader('Referer');
		$logger->debug('Referer:'.json_encode($referer));
		if (count($referer) > 0 && str_contains($referer[0], 'service-worker')) {
			$logger->debug('Blocked service worker');
			return $this->respond(new ErrorResponse('Blocked service worker request'), 403);
		}

		$user = $this->auth->getLoggedIn();
		if ($user === null) {
			return $this->respond(new ErrorResponse('User is not logged in', ErrorType::ACCESS), 401);
		}

		if (!$this->auth->hasRight('manage-arenas') && $arena->user?->id !== $user->id) {
			return $this->respond(new ErrorResponse('Cannot generate auth for this arena', ErrorType::ACCESS), 403);
		}

		$arena->clearCache();
		$client = $this->clientFactory->getClient($arena, true);
		$url = $client->createAuthUrl();
		$logger->info('Redirecting to Google OAuth URL: '.$url);
		return $this->redirect(new Uri($url));
	}

	public function auth(Arena $arena, Request $request): ResponseInterface {
		$client = $this->clientFactory->getClient($arena);

		/** @var string|null $code */
		$code = $request->getGet('code');
		if (empty($code)) {
			return $this->respond(new ErrorResponse('Missing code parameter', ErrorType::VALIDATION), 400);
		}

		// Validate logged-in user
		$user = $this->auth->getLoggedIn();
		if ($user === null) {
			return $this->respond(new ErrorResponse('User is not logged in', ErrorType::ACCESS), 401);
		}
		if ($arena->user?->id !== $user->id && !$this->auth->hasRight('manage-arenas')) {
			return $this->respond(new ErrorResponse('Cannot generate auth for this arena', ErrorType::ACCESS), 403);
		}

		/** @var array{access_token:string,refresh_token:string} $token */
		$token = $client->fetchAccessTokenWithAuthCode($code);
		$arena->fetch(true);
		$arena->googleSettings->accessToken = $token;
		$arena->save();

		return $this->redirect($client->createAuthUrl());
	}

}