<?php
declare(strict_types=1);

namespace App\Services\Dropbox;

use App\Models\Arena;
use App\Response\Dropbox\TokenResponse;
use DateTimeImmutable;
use GrahamCampbell\GuzzleFactory\GuzzleFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Lsr\Core\App;
use Lsr\Logging\Logger;
use Spatie\Dropbox\RefreshableTokenProvider;
use Symfony\Component\Serializer\Serializer;

class TokenProvider implements RefreshableTokenProvider
{

	private readonly Client $client;

	public function __construct(
		private readonly Arena $arena,
	) {
		$this->client = new Client(['handler' => GuzzleFactory::handler()]);
	}

	/**
	 * @inheritDoc
	 */
	public function refresh(ClientException $exception): bool {
		return $this->oAuthToken();
	}

	public function oAuthToken() : bool {
		$logger = new Logger(LOG_DIR, 'dropbox-oauth');
		$options = [
			'headers' => [],
			'form_params' => [
				'client_id' => $this->arena->dropbox->appId,
			],
		];

		if ($this->arena->dropbox->refreshToken !== null) {
			$options['form_params']['grant_type'] = 'refresh_token';
			$options['form_params']['refresh_token'] = $this->arena->dropbox->refreshToken;
		}
		else {
			$options['form_params']['code'] = $this->arena->dropbox->apiKey;
			$options['form_params']['grant_type'] = 'authorization_code';
			$options['form_params']['code_verifier'] = $this->arena->dropbox->authChallenge;
			$options['form_params']['redirect_uri'] = App::getLink(['dropbox', (string)$this->arena->id, 'auth']);
		}

		$logger->info('New dropbox token request "'.$options['form_params']['grant_type'].'" for arena '.$this->arena->id);

		try {
			$response = $this->client->post('https://api.dropboxapi.com/oauth2/token', $options);
		} catch (GuzzleException $e) {
			$logger->exception($e);
			$logger->debug('Request', $options);
			return false;
		}
		$serializer = App::getService('symfony.serializer');
		assert($serializer instanceof Serializer);
		if ($response->getStatusCode() !== 200) {
			$logger->error('Api call failed (' . $response->getStatusCode() . ') ' . $response->getBody()->getContents());
			return false;
		}
		$contents = $response->getBody()->getContents();
		$logger->debug($contents);
		$data = $serializer->deserialize($contents, TokenResponse::class, 'json');
		bdump($data);
		$logger->debug('Data:', get_object_vars($data));

		// Update refresh token if provided
		if ($data->refreshToken !== null) {
			$this->arena->dropbox->refreshToken = $data->refreshToken;
		}

		// Update access token
		$this->arena->dropbox->apiKey = $data->accessToken;
		$this->arena->dropbox->apiKeyValid = new DateTimeImmutable('+'.$data->expiresIn.' seconds');

		// Clear the auth challenge
		$this->arena->dropbox->authChallenge = null;

		// Save
		return $this->arena->save();
	}

	public function getToken(): string {
		return $this->arena->dropbox->apiKey;
	}
}