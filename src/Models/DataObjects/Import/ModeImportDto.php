<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import;

use App\GameModels\Game\Enums\GameModeType;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ModeImport')]
class ModeImportDto
{
	#[OA\Property]
	public ?GameModeType $type = null;
	#[OA\Property(example: 'Team deathmach')]
	public string $name;
}