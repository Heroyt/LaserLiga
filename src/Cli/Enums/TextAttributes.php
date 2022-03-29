<?php

namespace App\Cli\Enums;

/**
 * List of CLI font attributes that can be applied to stdout
 */
enum TextAttributes: string
{

	case BOLD = "\e[1m";
	case UN_BOLD = "\e[21m";
	case DIM = "\e[2m";
	case UN_DIM = "\e[22m";
	case UNDERLINED = "\e[4m";
	case UN_UNDERLINED = "\e[24m";
	case BLINK = "\e[5m";
	case UN_BLINK = "\e[25m";
	case REVERSE = "\e[7m";
	case UN_REVERSE = "\e[27m";
	case HIDDEN = "\e[8m";
	case UN_HIDDEN = "\e[28m";

}