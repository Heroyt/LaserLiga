<?php

namespace App\Services;

use App\Helpers\Gender;
use Lsr\Helpers\Tools\Strings;

class GenderService
{

	private const array SCORED_WORDS = [
		'pan'   => 20,
		'boy'   => 5,
		'guy'   => 5,
		'pani'  => -20,
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
			$weight = strlen($word) < 3 ? 0.5 : 1;
			$score += self::checkWords($word);
			switch (self::checkSuffix($word)) {
				case Gender::MALE:
					$score += $weight;
					break;
				case Gender::FEMALE:
					$score -= $weight;
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
		$contents = file_get_contents(ROOT . 'include/data/man_vs_woman_suffixes.txt');
		assert($contents !== false, 'Cannot read file');
		self::$suffixes ??= unserialize(
			$contents,
			['allowed_classes' => false]
		);
		return self::$suffixes;
	}

}