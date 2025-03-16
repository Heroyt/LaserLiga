<?php

declare(strict_types=1);

namespace App\Models;

use Lsr\Core\Models\WithCacheClear;
use Lsr\Orm\Model;

abstract class BaseModel extends Model
{
	use WithCacheClear;
}
