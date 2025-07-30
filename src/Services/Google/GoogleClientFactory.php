<?php
declare(strict_types=1);

namespace App\Services\Google;

use App\Models\Arena;
use Google\Client;
use Google\Service\Calendar;
use Lsr\Core\Links\Generator;

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

	public function getClient(Arena $arena, bool $recreate = false): Client {
		if (!$recreate && isset($this->clients[$arena->id])) {
			return $this->clients[$arena->id];
		}

		$client = new Client();
		$client->setApplicationName($this->applicationName);
		$client->setScopes([
			                   Calendar::CALENDAR,
			                   Calendar::CALENDAR_CALENDARLIST,
			                   Calendar::CALENDAR_EVENTS,
		                   ]);
		$client->setAccessToken('offline');
		$client->setPrompt('consent');
		$client->setIncludeGrantedScopes(true);

		$client->setAuthConfig($this->authConfig);
		$client->setRedirectUri($this->linkGenerator->getLink(['google', (string)$arena->id, 'auth']));

		if ($arena->googleSettings->isReady()) {
			$client->setAccessToken($arena->googleSettings->accessToken);
		}

		$this->clients[(int)$arena->id] = $client;
		return $client;
	}

}