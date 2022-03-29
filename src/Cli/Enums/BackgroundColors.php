<?php

namespace App\Cli\Enums;

/**
 * List of CLI background colors and their values
 */
enum BackgroundColors: string
{

	case BLACK = "\033[40m";
	case RED = "\033[41m";
	case GREEN = "\033[42m";
	case YELLOW = "\033[43m";
	case BLUE = "\033[44m";
	case MAGENTA = "\033[45m";
	case CYAN = "\033[46m";
	case LIGHT_GRAY = "\033[47m";
	case DEFAULT = "\033[49m";
	case DARK_GRAY = "\e[100m";
	case LIGHT_RED = "\e[101m";
	case LIGHT_GREEN = "\e[102m";
	case LIGHT_YELLOW = "\e[103m";
	case LIGHT_BLUE = "\e[104m";
	case LIGHT_MAGENTA = "\e[105m";
	case LIGHT_CYAN = "\e[106m";
	case WHITE = "\e[107m";

}