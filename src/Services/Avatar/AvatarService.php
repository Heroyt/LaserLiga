<?php

namespace App\Services\Avatar;

class AvatarService
{

	public const BASE_API = 'https://api.dicebear.com/7.x/';

	/**
	 * Get an avatar using a dicebear API
	 *
	 * @param string     $seed
	 * @param AvatarType $type
	 *
	 * @return string
	 * @see https://www.dicebear.com
	 */
	public function getAvatar(string $seed, AvatarType $type): string {
		$url = $this::BASE_API . $type->value . '/svg?seed=' . urlencode($seed) . '&radius=50';
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		$responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		if ($responseCode !== 200 || !is_string($response)) {
			throw new \RuntimeException(
				'Getting an avatar failed (' . $url . ' err ' . $responseCode . ') ' . $response
			);
		}
		$svg = simplexml_load_string($response);
		$svg['class'] = 'player-avatar';
		return $svg->asXML();
	}

}