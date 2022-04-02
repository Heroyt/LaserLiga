<?php

namespace App\Services;

class Timer
{

	/** @var array{start:float,end:float}[] */
	public static array $timers = [];

	public static function start(string $name) : void {
		self::$timers[$name] = [
			'start' => microtime(true),
			'end'   => 0.0,
		];
	}

	public static function stop(string $name) : void {
		if (!isset(self::$timers[$name])) {
			return;
		}
		self::$timers[$name]['end'] = microtime(true);
	}

	public static function get(string $name) : float {
		if (!isset(self::$timers[$name])) {
			return 0.0;
		}
		return self::$timers[$name]['end'] - self::$timers[$name]['start'];
	}

}