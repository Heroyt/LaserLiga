<?php

namespace App\Cli;

use App\Cli\Enums\BackgroundColors;
use App\Cli\Enums\ForegroundColors;
use App\Cli\Enums\TextAttributes;

/**
 * CLI helper class for dealing with command line colors
 */
class Colors
{

	public const COLOR_RESET = "\033[0m";

	/**
	 * Set an output color to
	 *
	 * @param ForegroundColors|null $foreground
	 * @param BackgroundColors|null $background
	 * @param TextAttributes|null   $attribute
	 *
	 * @return string
	 */
	public static function color(?ForegroundColors $foreground = null, ?BackgroundColors $background = null, ?TextAttributes $attribute = null) : string {
		$return = '';
		if (isset($attribute)) {
			$return .= $attribute->value;
		}
		if (isset($foreground)) {
			$return .= $foreground->value;
		}
		if (isset($background)) {
			$return .= $background->value;
		}
		return $return;
	}

	public static function reset() : string {
		return self::COLOR_RESET;
	}

}