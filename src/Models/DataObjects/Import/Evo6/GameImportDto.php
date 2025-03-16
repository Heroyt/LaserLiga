<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import\Evo6;

use Lsr\Lg\Results\LaserMaxx\Evo6\GameStyleType;
use Lsr\Lg\Results\LaserMaxx\Evo6\HitGainSettings;
use Lsr\Lg\Results\LaserMaxx\Evo6\RespawnSettings;
use Lsr\Lg\Results\LaserMaxx\Evo6\Scoring;
use Lsr\Lg\Results\LaserMaxx\Evo6\TriggerSpeed;
use Lsr\Lg\Results\LaserMaxx\Evo6\VipSettings;
use Lsr\Lg\Results\LaserMaxx\Evo6\ZombieSettings;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Evo6GameImport')]
class GameImportDto extends \App\Models\DataObjects\Import\Lasermaxx\GameImportDto
{
	#[OA\Property]
	public ?Scoring $scoring = null;
	/** @var PlayerImportDto[] */
	#[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/Evo6PlayerImport'))]
	public array $players = [];

	#[OA\Property]
	public TriggerSpeed $triggerSpeed = TriggerSpeed::FAST;

	#[OA\Property]
	public GameStyleType $gameStyleType = GameStyleType::TEAM;

	#[OA\Property]
	public ?VipSettings $vipSettings = null;
	#[OA\Property]
	public ?ZombieSettings $zombieSettings = null;
	#[OA\Property]
	public ?HitGainSettings $hitGainSettings = null;
	#[OA\Property]
	public ?RespawnSettings $respawnSettings = null;

	public function addPlayer(PlayerImportDto $player): void {
		$this->players[] = $player;
	}
}