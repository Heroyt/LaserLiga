<?php
declare(strict_types=1);

namespace App\Templates\Login;

use App\Models\Arena;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class LoginParams extends TemplateParameters
{
	use PageTemplateParameters;

	public string $turnstileKey;
	/** @var Arena[] */
	public array $arenas = [];

}