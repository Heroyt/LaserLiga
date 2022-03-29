<?php

namespace App\Cli\Enums;

/**
 * List of CLI foreground colors and their values
 */
enum ForegroundColors: string
{

	case BLACK = "\033[0;30m";
	case DARK_GRAY = "\033[1;30m";
	case RED = "\033[0;31m";
	case LIGHT_RED = "\033[1;31m";
	case GREEN = "\033[0;32m";
	case LIGHT_GREEN = "\033[1;32m";
	case YELLOW = "\033[0;33m";
	case BLUE = "\033[0;34m";
	case LIGHT_BLUE = "\033[1;34m";
	case MAGENTA = "\033[0;35m";
	case PURPLE = "\033[2;35m";
	case LIGHT_PURPLE = "\033[1;35m";
	case CYAN = "\033[0;36m";
	case LIGHT_CYAN = "\033[1;36m";
	case LIGHT_GRAY = "\033[2;37m";
	case BOLD_WHITE = "\033[1;38m";
	case WHITE = "\033[0;38m";
	case DEFAULT = "\033[39m";
	case GRAY = "\033[0;90m";
	case LIGHT_RED_ALT = "\033[91m";
	case LIGHT_GREEN_ALT = "\033[92m";
	case LIGHT_YELLOW_ALT = "\033[93m";
	case LIGHT_YELLOW = "\033[1;93m";
	case LIGHT_BLUE_ALT = "\033[94m";
	case LIGHT_MAGENTA_ALT = "\033[95m";
	case LIGHT_CYAN_ALT = "\033[96m";
	case LIGHT_WHITE_ALT = "\033[97m";

}