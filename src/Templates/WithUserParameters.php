<?php
declare(strict_types=1);

namespace App\Templates;

use App\Models\Auth\User;

trait WithUserParameters
{

	public ?User $user = null;

}