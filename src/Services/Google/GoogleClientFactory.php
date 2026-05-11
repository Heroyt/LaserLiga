<?php
declare(strict_types=1);

namespace App\Services\Google;

use App\Models\Arena;
use Google\Client;
use Google\Exception;
use Google\Service\Calendar;
use Lsr\Core\Links\Generator;
use Lsr\Logging\Logger;

final class GoogleClientFactory
{

	/** @var array<int,Client> */
	private array $clients = [];

	/**
	 * @param array{
	 *     web:array{
	 *          client_id:string,
	 *          project_id:string,
	 *          auth_uri:string,
	 *          token_uri:string,
	 *          auth_provider_x509_cert_url:string,
	 *          client_secret:string,
	 *          redirect_uris:string[],
	 *          javascript_origins:string[]
	 *     }
	 *   } $authConfig
	 */
	public function __construct(
		private readonly Generator $linkGenerator,
		private readonly array     $authConfig,
		private readonly string    $applicationName = 'LaserLiga',
	) {
	}

	public function getClient(Arena $arena, bool $recreate = false, bool $skipAuth = false): Client {
		if (!$recreate && isset($this->clients[$arena->id])) {
			return $this->clients[$arena->id];
		}

		$logger = new Logger(LOG_DIR.'google/', 'client');

		$client = new Client();
		$client->setApplicationName($this->applicationName);
		$client->setScopes([
			                   Calendar::CALENDAR,
			                   Calendar::CALENDAR_CALENDARLIST,
			                   Calendar::CALENDAR_EVENTS,
		                   ]);
		$client->setAccessType('offline');
		$client->setPrompt('consent');
		$client->setIncludeGrantedScopes(true);

		$redirectUri = $this->linkGenerator->getLink(['google', (string)$arena->id, 'auth']);
		$auth = $this->authConfig;
		$auth['redirect_uris'][] = $redirectUri;

		try {
			$client->setAuthConfig($auth);
		} catch (Exception $e) {
			$logger->exception($e);
			throw $e;
		}
		$client->setRedirectUri($redirectUri);

		if (!$skipAuth && $arena->googleSettings->isReady()) {
			$client->setAccessToken($arena->googleSettings->accessToken);
		}

		$this->clients[(int)$arena->id] = $client;
		return $client;
	}

	public function maybeRefreshToken(Arena $arena, Client $client) : bool {
		/** @var array{access_token:string,expires_in:int,refresh_token:string,scope:string,token_type:string,refresh_token_expires_in:int,created:int} $clientToken */
		$clientToken = $client->getAccessToken();
		// If saved token is null or is younger than the client token, we need to update it
		if ($arena->googleSettings->accessToken !== null && $arena->googleSettings->accessToken['created'] >= $clientToken['created']) {
			return true;
		}

		$arena->googleSettings->accessToken = $clientToken;
		return $arena->save();
	}

}