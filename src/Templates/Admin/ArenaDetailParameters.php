<?php
declare(strict_types=1);

namespace App\Templates\Admin;

use App\Models\Arena;
use App\Models\DataObjects\Arena\ArenaApiKeyRow;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use App\Templates\WithUserParameters;
use Lsr\Core\Controllers\TemplateParameters;

class ArenaDetailParameters extends TemplateParameters
{
	use AutoFillParameters;
	use PageTemplateParameters;
	use WithUserParameters;

	public Arena $arena;
	/** @var ArenaApiKeyRow[]  */
	public array $apiKeys = [];

}