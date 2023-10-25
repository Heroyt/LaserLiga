<?php

use App\Exceptions\FileException;
use App\Models\DataObjects\Image;
use App\Services\ImageService;
use Lsr\Helpers\Tools\Strings;

/**
 * @file      functions.php
 * @brief     Main functions
 * @details   File containing all main functions for the app.
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */

function comparePlayerNames(string $name1, string $name2): bool {
	$name1 = strtolower(trim(Strings::toAscii($name1)));
	$name2 = strtolower(trim(Strings::toAscii($name2)));
	return $name1 === $name2;
}

/**
 * Get a formatted, translated string containing the duration
 *
 * @param int $minutes
 *
 * @return string
 */
function convertMinutesToPartsReadableString(int $minutes): string {
	[$days, $hours, $minutes] = convertMinutesToParts($minutes);
	$return = [];
	if ($days > 0) {
		$return[] = sprintf(lang('%d den', '%d dní', $days), $days);
	}
	if ($hours > 0) {
		$return[] = sprintf(lang('%d hodina', '%d hodin', $hours), $hours);
	}
	if ($minutes > 0 || count($return) === 0) {
		$return[] = sprintf(lang('%d minuta', '%d minut', $minutes), $minutes);
	}
	return implode(' ', $return);
}

/**
 * @param int $minutes
 *
 * @return array{0:int,1:int,2:int} Days, hours, minutes
 */
function convertMinutesToParts(int $minutes): array {
	// 1440 = 60*24 minutes in a day
	$days = (int)floor($minutes / 1440);
	$minutes %= 1440; // Get remaining minutes
	// 60 = minutes in an hour
	$hours = (int)floor($minutes / 60);
	$minutes %= 60; // Get remaining minutes
	return [$days, $hours, $minutes];
}

/**
 * @param string               $name
 * @param int|float            $size
 * @param int|float            $x
 * @param int|float            $y
 * @param array<string,string> $attrs
 * @param bool                 $invertColors
 *
 * @return string
 * @throws FileException
 */
function svgIconThumb(string $name, int|float $size, int|float $x = 0, int|float $y = 0, array $attrs = [], bool $invertColors = false): string {
	$file = ASSETS_DIR . 'icons/' . $name . '.svg';
	if (!file_exists($file)) {
		throw new InvalidArgumentException('Icon "' . $name . '" does not exist in "' . ASSETS_DIR . 'icons/".');
	}
	$out = extractSvg(
		$file,
		[
			'width'  => $size,
			'height' => $size,
			'x'      => $x,
			'y'      => $y,
			'class'  => 'icon-' . $name,
		]
	);
	if ($invertColors) {
		$out = str_replace(['#fff', '#FFF', '#ffffff', '#FFFFFF', 'white', '#000', '#000000', 'black'],
		                   ['#000', '#000', '#000', '#000', '#000', '#fff', '#fff', '#fff'],
		                   $out);
	}
	return $out;
}

/**
 * @param string              $file
 * @param array<string,mixed> $attrs
 *
 * @return string
 * @throws FileException
 */
function extractSvg(string $file, array $attrs = []): string {
	if (!file_exists($file)) {
		throw new InvalidArgumentException('File "' . $file . '" does not exist..');
	}
	$contents = file_get_contents($file);
	if ($contents === false) {
		throw new FileException('Failed to read file ' . $file);
	}
	$xml = simplexml_load_string($contents);
	if ($xml === false) {
		throw new FileException('File (' . $file . ') does not contain valid SVG');
	}
	foreach ($attrs as $key => $value) {
		$xml[$key] = $value;
	}
	$out = $xml->asXML();
	if ($out === false) {
		return '';
	}
	return str_replace(
		[
			'<?xml version="1.0"?>',
			'<?xml version="1.0" encoding="iso-8859-1"?>',
			'<?xml version="1.0" encoding="utf-8"?>',
			'<?xml version="1.0" encoding="UTF-8" standalone="no"?>',
			'<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">',
		],
		'',
		$out);
}

function getSvgStringWidth(string $string, float $multiplier = 1): float {
	$letterWidth = [
		' ' => 6,
		'0' => 11,
		'1' => 11,
		'2' => 11,
		'3' => 11,
		'4' => 11,
		'5' => 11,
		'6' => 11,
		'7' => 11,
		'8' => 11,
		'9' => 11,
		'a' => 11,
		'á' => 11,
		'b' => 12,
		'c' => 11,
		'č' => 11,
		'd' => 12,
		'ď' => 12,
		'e' => 11,
		'é' => 11,
		'ě' => 11,
		'f' => 7,
		'g' => 12,
		'h' => 12,
		'i' => 6,
		'í' => 6,
		'j' => 6,
		'k' => 11,
		'l' => 6,
		'm' => 18,
		'n' => 12,
		'ň' => 12,
		'o' => 12,
		'ó' => 12,
		'p' => 12,
		'q' => 12,
		'r' => 8,
		'ř' => 8,
		's' => 11,
		'š' => 11,
		't' => 7,
		'ť' => 11,
		'u' => 12,
		'ú' => 12,
		'ů' => 12,
		'v' => 11,
		'w' => 16,
		'x' => 11,
		'y' => 11,
		'ý' => 11,
		'z' => 10,
		'ž' => 10,
		'A' => 14,
		'Á' => 14,
		'B' => 14,
		'C' => 14,
		'Č' => 14,
		'D' => 14,
		'Ď' => 14,
		'E' => 13,
		'É' => 13,
		'Ě' => 13,
		'F' => 12,
		'G' => 16,
		'H' => 14,
		'I' => 6,
		'Í' => 6,
		'J' => 11,
		'K' => 14,
		'L' => 12,
		'M' => 17,
		'N' => 14,
		'Ň' => 14,
		'O' => 16,
		'Ó' => 16,
		'P' => 13,
		'Q' => 16,
		'R' => 14,
		'Ř' => 14,
		'S' => 13,
		'Š' => 13,
		'T' => 12,
		'Ť' => 12,
		'U' => 14,
		'Ú' => 14,
		'Ů' => 14,
		'V' => 13,
		'W' => 19,
		'X' => 13,
		'Y' => 14,
		'Ý' => 14,
		'Z' => 12,
		'Ž' => 12,
	];
	$letters = str_split($string);
	$length = 0;
	foreach ($letters as $letter) {
		$length += ($letterWidth[$letter] ?? 11) * $multiplier;
	}
	return $length;
}

function autoParagraphs(string $text): string {
	$paragraphs = explode("\n\n", $text);
	return '<p>' . implode('</p><p>', $paragraphs) . '</p>';
}

function getImageSrcSet(Image|string $image): string {
	if (is_string($image)) {
		$image = new Image($image);
	}

	$versions = $image->getOptimized();

	$srcSet = [];

	foreach (array_reverse(ImageService::SIZES) as $size) {
		$index = $size . '-webp';
		if (isset($versions[$index])) {
			$srcSet[] = $versions[$index] . ' ' . $size . 'w';
			continue;
		}
		$index = (string)$size;
		if (isset($versions[$index])) {
			$srcSet[] = $versions[$index] . ' ' . $size . 'w';
		}
	}

	if (isset($versions['webp'])) {
		$srcSet[] = $versions['webp'];
	}
	else {
		$srcSet[] = $versions['original'];
	}

	return implode(',', $srcSet);
}