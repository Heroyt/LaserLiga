<?php

namespace App\Core;

use Dibi\Exception;

class Info
{

	public const TABLE = 'page_info';
	private static array $info = [];

	/**
	 * @param string     $key
	 * @param mixed|null $default
	 *
	 * @return mixed
	 */
	public static function get(string $key, mixed $default = null) : mixed {
		if (isset(self::$info[$key])) {
			return self::$info[$key];
		}
		$value = DB::select(self::TABLE, '[value]')->where('[key] = %s', $key)->fetchSingle();
		if (!isset($value)) {
			return $default;
		}
		/** @noinspection UnserializeExploitsInspection */
		$value = unserialize($value);
		self::$info[$key] = $value; // Cache
		return $value;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function set(string $key, mixed $value) : void {
		self::$info[$key] = $value; // Cache
		$test = DB::select(self::TABLE, 'count(*)')->where('[key] = %s', $key)->fetchSingle();
		if ($test > 0) {
			DB::update(self::TABLE, [
				'value' => serialize($value),
			],         [
									 '[key] = %s',
									 $key
								 ]);
			return;
		}
		DB::insert(self::TABLE, [
			'key'   => $key,
			'value' => serialize($value),
		]);
	}

}