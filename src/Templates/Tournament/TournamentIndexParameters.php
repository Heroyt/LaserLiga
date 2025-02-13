<?php
declare(strict_types=1);

namespace App\Templates\Tournament;

use App\Models\Tournament\Tournament;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class TournamentIndexParameters extends TemplateParameters
{
	use PageTemplateParameters;
	use AutoFillParameters;

	public bool $planned = true;

	/** @var Tournament[] */
	public array $tournaments = [];

}