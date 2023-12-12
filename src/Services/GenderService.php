<?php

namespace App\Services;

use App\Helpers\Gender;
use Lsr\Helpers\Tools\Strings;

class GenderService
{

	private const SCORED_WORDS = [
		'pan'   => 5,
		'boy'   => 5,
		'guy'   => 5,
		'pani'  => -5,
		'girl'  => -5,
		'david' => 5,
		'tomáš' => 5,
		'adam'  => 5,
		'marek' => 5,
	];
	/** @var string[] */
	private static array $suffixes;
	/** @var array<string,Gender> */
	private static array $memory = [];

	public static function rankWord(string $string): Gender {
		if (preg_match('/^[^a-z\d_\s\-]*$/', $string) > 0) { // Is all uppercase
			$string = mb_strtolower($string);
		}
		$normalized = mb_strtolower(Strings::toSnakeCase(Strings::toAscii($string)));

		if (isset(self::$memory[$normalized])) {
			return self::$memory[$normalized];
		}

		// Separate one string into words
		$words = array_filter(explode('_', $normalized));

		$score = 0;
		foreach ($words as $word) {
			$score += self::checkWords($word);
			switch (self::checkSuffix($word)) {
				case Gender::MALE:
					++$score;
					break;
				case Gender::FEMALE:
					--$score;
					break;
				case Gender::OTHER:
					break;
			}
		}

		return match (true) {
			$score > 0 => Gender::MALE,
			$score < 0 => Gender::FEMALE,
			default    => Gender::OTHER,
		};
	}

	private static function checkWords(string $name): int {
		return self::SCORED_WORDS[$name] ?? 0;
	}

	private static function checkSuffix(string $name): Gender {
		$suffixes = self::getSuffixes();
		foreach (range(mb_strlen($name), 1) as $length) {
			$suffix = mb_substr($name, -1 * $length);
			//echo $suffix.PHP_EOL;
			if (array_key_exists($suffix, $suffixes)) {
				return match ($suffixes[$suffix]) {
					'm'     => Gender::MALE,
					'w'     => Gender::FEMALE,
					default => Gender::OTHER,
				};
			}
		}

		return Gender::OTHER;
	}

	/**
	 * @return string[]
	 */
	private static function getSuffixes(): array {
		self::$suffixes ??= unserialize(
			file_get_contents(ROOT . 'include/data/man_vs_woman_suffixes.txt'),
			['allowed_classes' => false]
		);
		return self::$suffixes;
	}

}