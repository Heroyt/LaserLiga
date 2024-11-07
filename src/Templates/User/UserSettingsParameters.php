<?php
declare(strict_types=1);

namespace App\Templates\User;

use App\Models\Achievements\Title;
use App\Models\Arena;
use App\Models\Auth\User;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class UserSettingsParameters extends TemplateParameters
{
	use PageTemplateParameters;

	public ?User $loggedInUser;
	public User $user;
	/** @var Arena[] */
	public array $arenas = [];
	/** @var Title[] */
	public array $titles = [];
}