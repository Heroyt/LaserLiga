<?php

namespace App\Services;

use App\Helpers\Gender;
use Lsr\Core\App;

/**
 * Mostly stolen from granam/czech-vocative
 *
 * @link https://github.com/granam/czech-vocative
 */
class NameInflectionService
{
	public const CASES                = [
		1 => 'nominative',
		2 => 'genitive',
		3 => 'dative',
		4 => 'accusative',
		5 => 'vocative',
		6 => 'locative',
		7 => 'instrumental',
	];
	public const INFLECTION_LANGUAGES = ['cs'];
	/** @var array<string,array{1?:string,2?:string,3?:string,4?:string,5?:string,6?:string,7?:string}> */
	private static array $memory = [];
	/** @var array{m:string[],f:string[],o:string[]}[] */
	private static array $suffixes = [
		1 => ['m' => [], 'f' => [], 'o' => []],
		2 => ['m' => [], 'f' => [], 'o' => []],
		3 => ['m' => [], 'f' => [], 'o' => []],
		4 => ['m' => [], 'f' => [], 'o' => []],
		5 => ['m' => [], 'f' => [], 'o' => []],
		6 => ['m' => [], 'f' => [], 'o' => []],
		7 => ['m' => [], 'f' => [], 'o' => []],
	];
	private static bool  $checkedLang;

	public static function nominative(string $name): string {
		return $name;
	}

	public static function genitive(string $name): string {
		return self::transform($name, 2);
	}

	/**
	 * @param string   $name
	 * @param int<1,7> $case
	 *
	 * @return string
	 */
	private static function transform(string $name, int $case): string {
		$name = trim($name);
		$memoryKey = $name;

		$isUppercase = preg_match('/^[^a-z\d_\s\-]*$/', $name) > 0;

		// Remove numbers from the end
		preg_match('/^(.*[^\d_\-\s])([_\-\s]*\d+)?$/', $name, $matches);
		$name = $matches[1] ?? '';
		$numbers = $matches[2] ?? '';

		if (str_ends_with($name, ' ')) {
			$numbers = ' ' . $numbers;
			$name = trim($name);
		}

		// Use cached results
		if (!isset(self::$memory[$memoryKey])) {
			self::$memory[$memoryKey] = [];
		}
		if (isset(self::$memory[$memoryKey][$case])) {
			return self::$memory[$memoryKey][$case];
		}
		if (!self::checkLang()) {
			self::$memory[$memoryKey][$case] = $name;
			return $name;
		}
		$key = mb_strtolower($name, 'UTF-8');
		$gender = GenderService::rankWord($name);

		[$match, $suffix] = self::getMatchingSuffix($key, self::getSuffixes($gender, $case));

		if ($match) {
			$name = mb_substr($name, 0, -1 * mb_strlen($match));
		}
		$name .= $suffix . $numbers;
		if ($isUppercase) {
			$name = mb_strtoupper($name);
		}
		self::$memory[$memoryKey][$case] = $name;
		return $name;
	}

	private static function checkLang(): bool {
		if (isset(self::$checkedLang)) {
			return self::$checkedLang;
		}
		self::$checkedLang = in_array(App::getShortLanguageCode(), self::INFLECTION_LANGUAGES, true);
		return self::$checkedLang;
	}

	/**
	 * @param string               $name
	 * @param array<string,string> $suffixes
	 *
	 * @return array{0:string,1:string}
	 */
	private static function getMatchingSuffix(string $name, array $suffixes): array {
		// it is important(!) to try suffixes from longest to shortest
		foreach (range(mb_strlen($name), 1) as $length) {
			$suffix = mb_substr($name, -1 * $length);
			if (array_key_exists($suffix, $suffixes)) {
				return [$suffix, $suffixes[$suffix]];
			}
		}

		return ['', $suffixes[''] ?? ''];
	}

	/**
	 * @param Gender   $gender
	 * @param int<1,7> $case
	 *
	 * @return array<string,string>
	 */
	private static function getSuffixes(Gender $gender, int $case): array {
		if (empty(self::$suffixes[$case][$gender->value])) {
			$file = ROOT . 'include/data/' . $gender->value . '_' . self::CASES[$case] . '_suffixes.txt';
			if (!file_exists($file)) {
				self::$suffixes[$case][$gender->value] = [];
				return [];
			}
			// @phpstan-ignore-next-line
			self::$suffixes[$case][$gender->value] = unserialize(file_get_contents($file), ['allowed_classes' => false]
			);
		}
		// @phpstan-ignore-next-line
		return self::$suffixes[$case][$gender->value];
	}

	public static function dative(string $name): string {
		return self::transform($name, 3);
	}

	public static function accusative(string $name): string {
		return self::transform($name, 4);
	}

	public static function vocative(string $name): string {
		return self::transform($name, 5);
	}

	public static function locative(string $name): string {
		return self::transform($name, 6);
	}

	public static function instrumental(string $name): string {
		return self::transform($name, 7);
	}


}