<?php

namespace App\Models\Achievements;

use App\Models\BaseModel;
use App\Models\Rarity;
use Lsr\Orm\Attributes\PrimaryKey;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_title'), OA\Schema]
class Title extends BaseModel
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

