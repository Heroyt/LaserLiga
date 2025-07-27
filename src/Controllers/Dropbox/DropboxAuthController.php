<?php
declare(strict_types=1);

namespace App\Controllers\Dropbox;

use App\Models\Arena;
use App\Request\Dropbox\AuthRequest;
use App\Services\Dropbox\TokenProvider;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\Logging\Logger;
use Psr\Http\Message\ResponseInterface;
use Random\Randomizer;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class DropboxAuthController extends Controller
{

	public function __construct(
		private readonly Auth                    $auth,
		private readonly RequestValidationMapper $requestMapper,
	) {
		
	}

	public function redirectToAuth(Arena $arena, Request $request): ResponseInterface {
		$logger = new Logger(LOG_DIR, 'dropbox-oauth');
		$logger->info('New oauth flow started for arena '.$arena->id);
		$referer = $request->getHeader('Referer');
		$logger->debug('Referer:'.json_encode($referer));
		if (count($referer) > 0 && str_contains($referer[0], 'service-worker')) {
			$logger->debug('Blocked service worker');
			return $this->respond(new ErrorResponse('Blocked service worker request'), 403);
		}
		if (empty($arena->dropbox->appId) || empty($arena->dropbox->secret)) {
			$logger->error('Dropbox AppID and secret is not setup');
			return $this->respond(new ErrorResponse('Dropbox App ID is not set', ErrorType::VALIDATION), 400);
		}

		$user = $this->auth->getLoggedIn();
		if ($user === null) {
			return $this->respond(new ErrorResponse('User is not logged in', ErrorType::ACCESS), 401);
		}

		if (!$this->auth->hasRight('manage-arenas') && $arena->user?->id !== $user->id) {
			return $this->respond(new ErrorResponse('Cannot generate auth for this arena', ErrorType::ACCESS), 403);
		}

		// Generate random code verifier for the PCKE flow ([0-9a-zA-Z\-\.\_\~], {43,128}) using the Random\Randomizer API.
		$arena->dropbox->authChallenge = $this->base64UrlEncode(new Randomizer()->getBytes(64));
		if (!$arena->save()) {
			return $this->respond(new ErrorResponse('Failed to save auth challenge', ErrorType::DATABASE), 500);
		}
		$arena->clearCache();
		$logger->debug('Code verifier: '.$arena->dropbox->authChallenge);
		$hashed = hash('sha256', $arena->dropbox->authChallenge, true);
		$logger->debug('Hashed: '.$codeChallenge);
		$codeChallenge = $this->base64UrlEncode($hashed);
		$logger->debug('Code challenge: '.$codeChallenge);

		$query = http_build_query(
			[
				'client_id'             => $arena->dropbox->appId,
				'response_type'         => 'code',
				'redirect_uri'          => App::getLink(['dropbox', (string)$arena->id, 'auth']),
				'token_access_type'     => 'offline',
				'code_challenge'        => $codeChallenge,
				'code_challenge_method' => 'S256',
				'state'                 => hash_hmac(
					'sha256',
					$arena->id . '-' . $arena->dropbox->appId . '-' . $user->id,
					$arena->dropbox->secret
				),
			]
		);
		$url = 'https://www.dropbox.com/oauth2/authorize?' . $query;
		$logger->debug('URL: '.$url);

		return $this->redirect($url);
	}

	private function base64UrlEncode(string $data) : string {
		return rtrim(strtr(base64_encode($data), '+/', '-_'),'=');
	}

	public function auth(Arena $arena, Request $request): ResponseInterface {
		// Validate arena settings
		if (empty($arena->dropbox->appId) || empty($arena->dropbox->secret)) {
			return $this->respond(new ErrorResponse('Dropbox App ID is not set', ErrorType::VALIDATION), 400);
		}

		// Parse request
		try {
			$data = $this->requestMapper->setRequest($request)->mapQueryToObject(AuthRequest::class);
		} catch (ExceptionInterface $e) {
			return $this->respond(new ErrorResponse($e->getMessage(), ErrorType::VALIDATION, exception: $e), 400);
		}

		// Validate logged-in user
		$user = $this->auth->getLoggedIn();
		if ($user === null) {
			return $this->respond(new ErrorResponse('User is not logged in', ErrorType::ACCESS), 401);
		}
		if (!$this->auth->hasRight('manage-arenas') && $arena->user?->id !== $user->id) {
			return $this->respond(new ErrorResponse('Cannot generate auth for this arena', ErrorType::ACCESS), 403);
		}

		// Validate state hash
		$state = hash_hmac(
			'sha256',
			$arena->id . '-' . $arena->dropbox->appId . '-' . $user->id,
			$arena->dropbox->secret
		);
		if (!hash_equals($data->state, $state)) {
			return $this->respond(new ErrorResponse('Invalid state', ErrorType::VALIDATION), 400);
		}

		// Call the token API
		$arena->fetch(true);
		$arena->dropbox->apiKey = $data->code;
		$arena->dropbox->refreshToken = null;
		$tokenProvider = new TokenProvider($arena);
		if (!$tokenProvider->oAuthToken()) {
			return $this->respond(new ErrorResponse('Unable to get the API access token'), 500);
		}

		return $this->respond(new SuccessResponse('Dropbox API authorized'));
	}

}