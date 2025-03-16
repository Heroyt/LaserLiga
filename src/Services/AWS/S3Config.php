<?php
declare(strict_types=1);

namespace App\Services\AWS;

use Aws\Credentials\CredentialsInterface;

/**
 * @property-read string $bucket
 * @property-read string $region
 * @property-read string $endpoint_url
 * @property-read CredentialsInterface $credentials
 */
readonly final class S3Config
{

	/**
	 * @param array<string,mixed> $config
	 */
	public function __construct(
		private array $config = [],
	) {}

	public function __get(string $name): mixed {
		return $this->config[$name] ?? null;
	}

}