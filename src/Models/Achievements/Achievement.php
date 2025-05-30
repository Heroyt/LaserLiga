<?php

namespace App\Models\Achievements;

use App\Models\BaseModel;
use App\Models\Rarity;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\OneToOne;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_achievement'), OA\Schema]
class Achievement extends BaseModel
{
	public const string TABLE = 'achievements';

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

	#[OA\Property]
	public bool $hidden = false;

	public function jsonSerialize(): array {
		$data = parent::jsonSerialize();
		$data['name'] = lang($data['name'], domain: 'achievements');
		$data['description'] = lang($data['description'], context: $this->name, domain: 'achievements');
		$data['info'] = lang($data['info'], context: 'info', domain: 'achievements');
		return $data;
	}

}