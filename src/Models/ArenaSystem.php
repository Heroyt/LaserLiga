<?php
declare(strict_types=1);

namespace App\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Model;

#[PrimaryKey('id_arena_system')]
class ArenaSystem extends Model
{

	public const string TABLE = 'arena_systems';

	#[ManyToOne]
	public Arena $arena;

	#[ManyToOne]
	public System $system;

	public bool $default = false;
	public bool $active = true;

}