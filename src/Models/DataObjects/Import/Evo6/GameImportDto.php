<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import\Evo6;

use App\GameModels\Game\Evo6\Scoring;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Evo6GameImport')]
class GameImportDto extends \App\Models\DataObjects\Import\GameImportDto
{
	#[OA\Property]
	public ?Scoring $scoring = null;
	/** @var PlayerImportDto[] */
	#[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/Evo6PlayerImport'))]
	public array $players = [];

	public function addPlayer(PlayerImportDto $player): void {
		$this->players[] = $player;
	}
}