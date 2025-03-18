<?php
declare(strict_types=1);

namespace App\Models\Extensions;

use DateTimeInterface;
use Dibi\Row;
use Lsr\ObjectValidation\Attributes\StringLength;
use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\Interfaces\InsertExtendInterface;
use OpenApi\Attributes as OA;
use SensitiveParameter;

#[OA\Schema]
class DropboxSettings implements InsertExtendInterface
{

	/**
	 * @param non-empty-string|null  $apiKey
	 * @param non-empty-string|null  $directory
	 * @param non-empty-string|null  $appId
	 * @param non-empty-string|null  $secret
	 * @param non-empty-string|null  $refreshToken
	 * @param DateTimeInterface|null $apiKeyValid
	 * @param non-empty-string|null  $authChallenge
	 */
	public function __construct(
		#[SensitiveParameter, JsonExclude]
		public ?string            $apiKey = null,
		#[OA\Property]
		public ?string            $directory = null,
		#[OA\Property]
		public ?string            $appId = null,
		#[SensitiveParameter, JsonExclude]
		public ?string            $secret = null,
		#[SensitiveParameter, JsonExclude]
		public ?string            $refreshToken = null,
		#[OA\Property]
		public ?DateTimeInterface $apiKeyValid = null,
		#[SensitiveParameter, JsonExclude, StringLength(min:43, max: 128)]
		public ?string            $authChallenge = null,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row): ?static {
		return new self(
			$row->dropbox_api_key ?? null,
			$row->dropbox_directory ?? null,
			$row->dropbox_app_id ?? null,
			$row->dropbox_secret ?? null,
			$row->dropbox_refresh_token ?? null,
			$row->dropbox_api_key_valid ?? null,
			$row->dropbox_auth_challenge ?? null,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data): void {
		$data['dropbox_api_key'] = $this->apiKey;
		$data['dropbox_directory'] = $this->directory;
		$data['dropbox_app_id'] = $this->appId;
		$data['dropbox_secret'] = $this->secret;
		$data['dropbox_refresh_token'] = $this->refreshToken;
		$data['dropbox_api_key_valid'] = $this->apiKeyValid;
		$data['dropbox_auth_challenge'] = $this->authChallenge;
	}
}