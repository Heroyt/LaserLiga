<?php

namespace App\Models\Achievements;

use App\Models\Rarity;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_title')]
class Title extends Model
{

	public const TABLE = 'titles';

	public string  $name;
	public ?string $description = '';
	public Rarity  $rarity      = Rarity::COMMON;

	public bool $unlocked = false;

}

