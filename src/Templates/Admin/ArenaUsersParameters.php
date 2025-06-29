<?php
declare(strict_types=1);

namespace App\Templates\Admin;

use App\Models\Arena;
use App\Models\Auth\User;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class ArenaUsersParameters extends TemplateParameters
{
	use AutoFillParameters;
	use PageTemplateParameters;

	public Arena $arena;
	public User $user;

}