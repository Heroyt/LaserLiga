<?php
declare(strict_types=1);

namespace App\Templates\Arena;

use App\Models\Arena;
use App\Templates\GameFilters;
use App\Templates\AutoFillParameters;
use Lsr\Core\Controllers\TemplateParameters;

class ArenaGamesParameters extends TemplateParameters
{
	use AutoFillParameters;
	use GameFilters;

	public Arena $arena;
}