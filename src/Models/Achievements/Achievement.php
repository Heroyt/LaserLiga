<?php

namespace App\Models\Achievements;

use App\Models\Rarity;
use Lsr\Core\Models\Attributes\OneToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_achievement'), OA\Schema]
class Achievement extends Model
{
	public const TABLE = 'achievements';

	#[OA\Property]
	public ?string $icon        = null;
	#[OA\Property]
	public string  $name;
	#[OA\Property]
	public ?string $description = '';
	#[OA\Property]
	public ?string $info        = null;

	#[OA\Property]
	public AchievementType $type;
	#[OA\Property]
	public Rarity          $rarity = Rarity::COMMON;

	#[OA\Property]
	public ?int    $value = null;
	#[OA\Property]
	public ?string $key   = null;

	#[OA\Property]
	public bool $getAvatar = false;

	#[OneToOne]
	#[OA\Property]
	public ?Title $title = null;

	#[OA\Property]
	public bool $group = true;

}