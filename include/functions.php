<?php

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

function comparePlayerNames(string $name1, string $name2) : bool {
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
function convertMinutesToPartsReadableString(int $minutes) : string {
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
function convertMinutesToParts(int $minutes) : array {
	// 1440 = 60*24 minutes in a day
	$days = (int) floor($minutes / 1440);
	$minutes %= 1440; // Get remaining minutes
	// 60 = minutes in an hour
	$hours = (int) floor($minutes / 60);
	$minutes %= 60; // Get remaining minutes
	return [$days, $hours, $minutes];
}