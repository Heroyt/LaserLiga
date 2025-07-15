<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum IconType : int
{
	case SVG = 0;
	case FONTAWESOME = 1;
}
