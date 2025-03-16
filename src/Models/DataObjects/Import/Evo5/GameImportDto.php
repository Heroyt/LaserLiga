<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import\Evo5;

use Lsr\Lg\Results\LaserMaxx\Evo5\Scoring;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Evo5GameImport')]
class GameImportDto extends \App\Models\DataObjects\Import\Lasermaxx\GameImportDto
{
	#[OA\Property]
	public ?Scoring $scoring = null;
	/** @var PlayerImportDto[] */
	#[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/Evo5PlayerImport'))]
	public array $players = [];

	public function addPlayer(PlayerImportDto $player): void {
		$this->players[] = $player;
	}
}