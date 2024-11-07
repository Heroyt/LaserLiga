<?php
declare(strict_types=1);

namespace App\Templates\Index;

use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class IndexParameters extends TemplateParameters
{
	use PageTemplateParameters;

	public int $playerCount;
	public int $arenaCount;
	public int $tournamentCount;
	public int $gameCount;
}