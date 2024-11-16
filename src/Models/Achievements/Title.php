<?php

namespace App\Models\Achievements;

use App\Models\Rarity;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_title'), OA\Schema]
class Title extends Model
{

	public const string TABLE = 'titles';

	#[OA\Property]
	public string  $name;
	#[OA\Property]
	public ?string $description = '';
	#[OA\Property]
	public Rarity  $rarity      = Rarity::COMMON;

	#[OA\Property]
	public bool $unlocked = false;

}

