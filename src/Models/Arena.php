<?php

namespace App\Models;

use App\Core\AbstractModel;
use App\Core\DB;
use App\Core\Interfaces\InsertExtendInterface;
use App\Exceptions\ModelNotFoundException;
use App\Logging\DirectoryCreationException;
use Dibi\Exception;
use Dibi\Row;
use RuntimeException;

/**
 *
 */
class Arena extends AbstractModel implements InsertExtendInterface
{

	public const TABLE       = 'arenas';
	public const PRIMARY_KEY = 'id_arena';

	public const DEFINITION = [
		'name' => ['validators' => ['required']],
		'lat'  => [],
		'lng'  => [],
	];

	public string $name;
	public ?float $lat = null;
	public ?float $lng = null;

	/**
	 * Try to get the Arena object for given API key
	 *
	 * @param string $key
	 *
	 * @return Arena|null
	 */
	public static function getForApiKey(string $key) : ?Arena {
		$id = self::checkApiKey($key);
		if (isset($id)) {
			try {
				return new Arena($id);
			} catch (ModelNotFoundException|DirectoryCreationException $e) {
			}
		}
		return null;
	}

	/**
	 * Checks if the API key exists and is valid
	 *
	 * @param string $key
	 *
	 * @return int|null Arena's ID or null if the key does not exist or is invalid
	 */
	public static function checkApiKey(string $key) : ?int {
		return DB::select('api_keys', 'id_arena')->where('[key] = %s AND [valid] = 1', $key)->fetchSingle();
	}

	/**
	 * Parse data from DB into the object
	 *
	 * @param Row $row Row from DB
	 *
	 * @return Arena|null
	 */
	public static function parseRow(Row $row) : ?InsertExtendInterface {
		if (isset($row->{self::PRIMARY_KEY})) {
			try {
				return new self($row->{self::PRIMARY_KEY});
			} catch (ModelNotFoundException|DirectoryCreationException $e) {
			}
		}
		return null;
	}

	/**
	 * Generate a new unique API key for arena
	 *
	 * @param string|null $name Optional key name
	 *
	 * @return string Generated key
	 * @throws Exception If the key cannot be saved into the DB
	 */
	public function generateApiKey(?string $name = null) : string {
		if (!isset($this->id)) {
			throw new RuntimeException('Cannot generate API key for non-saved arena.');
		}

		// Generate a unique key
		do {
			// 33 bytes should be precisely 44 characters long when base64 encoded
			$key = base64_encode(random_bytes(33));
		} while (self::checkApiKey($key) !== null);

		// Save the key into DB
		DB::insert('api_keys', [
			'id_arena' => $this->id,
			'key'      => $key,
			'name'     => $name,
		]);

		return $key;
	}

	/**
	 * Add data from the object into the data array for DB INSERT/UPDATE
	 *
	 * @param array $data
	 */
	public function addQueryData(array &$data) : void {
		$data[self::PRIMARY_KEY] = $this->id;
	}
}