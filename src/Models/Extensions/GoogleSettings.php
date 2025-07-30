<?php
declare(strict_types=1);

namespace App\Models\Extensions;

use Dibi\Row;
use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\Interfaces\InsertExtendInterface;
use OpenApi\Attributes as OA;

#[OA\Schema]
class GoogleSettings implements InsertExtendInterface
{

	/**
	 * @param array{access_token:string,refresh_token:string}|null $accessToken
	 */
	public function __construct(
		#[\SensitiveParameter, JsonExclude]
		public ?array $accessToken = null,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row): ?static {
		$token = null;
		if (!empty($row->google_access_token)) {
			try {
				$token = json_decode($row->google_access_token, true, 512, JSON_THROW_ON_ERROR);
			} catch (\JsonException) {
				// Invalid JSON, ignore
			}
		}
		return new self(
			$token,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data): void {
		$data['google_access_token'] = empty($this->accessToken) ? null
			: json_encode($this->accessToken, JSON_THROW_ON_ERROR);
	}

	public function isReady(): bool {
		return !empty($this->accessToken);
	}
}