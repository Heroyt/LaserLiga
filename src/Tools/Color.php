<?php

namespace App\Tools;

use App\GameModels\Game\Game;

class Color
{

	/**
	 * @param Game[] $games
	 *
	 * @return string
	 */
	public static function getGamesColor(array $games) : string {
		$styles = [];
		foreach ($games as $game) {
			if (isset($styles[$game::SYSTEM])) {
				continue;
			}
			$styles[$game::SYSTEM] = $game::getTeamColors();
		}
		$classes = '';
		$return = '<style>:root{';
		foreach ($styles as $system => $colors) {
			$system = Strings::toSnakeCase($system, '-');
			foreach ($colors as $key => $color) {
				$fontColor = self::getFontColor($color);
				$var = 'team-'.$system.'-'.$key;
				$return .= '--'.$var.': '.$color.';';
				$classes .= '.bg-'.$var.'{background-color: var(--'.$var.');color:'.$fontColor.';}';
			}
		}
		$return .= '}'.$classes.'</style>';

		return $return;
	}

	/**
	 * Get the font color for given background color
	 *
	 * @param string $backgroundColor
	 * @param bool   $returnHex If true, return get string ("#000" or "#fff"), else return classname ("text-dark", "text-light")
	 *
	 * @return string
	 */
	public static function getFontColor(string $backgroundColor, bool $returnHex = true) : string {
		if ($backgroundColor[0] === '#') {
			$backgroundColor = substr($backgroundColor, 1);
		}
		$r = $g = $b = 0;
		switch (strlen($backgroundColor)) {
			case 3:
				$r = hexdec($backgroundColor[0].$backgroundColor[0]);
				$g = hexdec($backgroundColor[1].$backgroundColor[1]);
				$b = hexdec($backgroundColor[2].$backgroundColor[2]);
				break;
			case 6:
				$r = hexdec(substr($backgroundColor, 0, 2));
				$g = hexdec(substr($backgroundColor, 2, 2));
				$b = hexdec(substr($backgroundColor, 4, 2));
				break;
		}
		$luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
		if ($luminance > 0.5) {
			return $returnHex ? '#000' : 'text-dark';
		}

		return $returnHex ? '#fff' : 'text-light';
	}

}