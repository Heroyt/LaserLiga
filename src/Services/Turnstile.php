<?php

declare(strict_types=1);

namespace App\Services;

final readonly class Turnstile
{
	private const string TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	public const string  INPUT_NAME           = 'cf-turnstile-response';

	public function __construct(
		#[\SensitiveParameter]
		private string $secret,
		#[\SensitiveParameter]
		private string $key,
		private bool   $enabled = true,
	)
	{
	}

	/**
	 * Validate Turnstile captcha
	 *
	 * @param string|null $token
	 *
	 * @return bool
	 * @throws \JsonException
	 */
	public function validate(?string $token): bool
	{
		if (!$this->enabled) {
			return true;
		}
		$ch = curl_init(self::TURNSTILE_VERIFY_URL);
		if ($ch === false) {
			return false;
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, [
			'secret' => $this->secret,
			'response' => $token,
		]);
		/** @var string $data */
		$data = curl_exec($ch);
		/** @var array{success: bool} $result */
		$result = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
		curl_close($ch);
		return $result['success'];
	}

	public function getKey(): string
	{
		return $this->key;
	}
}
