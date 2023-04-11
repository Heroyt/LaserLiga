<?php

namespace App\Models\Tournament;

use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_group')]
class Group extends Model
{

	public const TABLE = 'tournament_groups';

	public string     $name;
	#[ManyToOne]
	public Tournament $tournament;

}