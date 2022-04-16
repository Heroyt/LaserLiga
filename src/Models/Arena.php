<?php

namespace App\Models;

use App\Core\AbstractModel;
use App\Core\App;
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

	/**
	 * Checks there exists an image of the arena
	 *
	 * The image must be either SVG or PNG. If no logo image exists, returns empty string;
	 *
	 * @return string Full path to image
	 */
	public function getLogoFileName() : string {
		$imageBase = ASSETS_DIR.'arena-logo/arena-'.$this->id;
		if (file_exists($imageBase.'.svg')) {
			return $imageBase.'.svg';
		}
		if (file_exists($imageBase.'.png')) {
			return $imageBase.'.png';
		}
		return '';
	}

	/**
	 * Checks there exists an image of the arena
	 *
	 * The image must be either SVG or PNG. If no logo image exists, returns empty string;
	 *
	 * @return string URL of the image
	 */
	public function getLogoUrl() : string {
		$image = $this->getLogoFileName();
		if (empty($image)) {
			return '';
		}
		return str_replace(ROOT, App::getUrl(), $image);
	}

	/**
	 * Gets HTML for displaying the arena image
	 *
	 * For SVG images, it returns the SVG XML, for other formats, it returns the <img> tag.
	 *
	 * @return string HTML or empty string if no logo exists
	 */
	public function getLogoHtml() : string {
		$image = $this->getLogoFileName();
		if (empty($image)) {
			return '';
		}
		$type = pathinfo($image, PATHINFO_EXTENSION);
		if ($type === 'svg') {
			return file_get_contents($image);
		}
		return '<img src="'.str_replace(ROOT, App::getUrl(), $image).'" class="img-fluid arena-logo" alt="'.$this->name.' - Logo" id="arena-logo-'.$this->id.'" />';
	}
}