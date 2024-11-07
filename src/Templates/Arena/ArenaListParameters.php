<?php
declare(strict_types=1);

namespace App\Templates\Arena;

use App\Models\Arena;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class ArenaListParameters extends TemplateParameters
{
	use PageTemplateParameters;
	use AutoFillParameters;

	/** @var Arena[] */
	public array $arenas = [];

}