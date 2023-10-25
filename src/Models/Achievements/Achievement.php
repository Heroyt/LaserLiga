<?php

namespace App\Models\Achievements;

use App\Models\Rarity;
use Lsr\Core\Models\Attributes\OneToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_achievement')]
class Achievement extends Model
{
	public const TABLE = 'achievements';

	public ?string $icon        = null;
	public string  $name;
	public ?string $description = '';
	public ?string $info        = null;

	public AchievementType $type;
	public Rarity          $rarity = Rarity::COMMON;

	public ?int    $value = null;
	public ?string $key   = null;

	public bool $getAvatar = false;

	#[OneToOne]
	public ?Title $title = null;

	public bool $group = true;

}