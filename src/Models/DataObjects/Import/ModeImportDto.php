<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import;

use App\GameModels\Game\ModeSettings;
use Lsr\Lg\Results\Enums\GameModeType;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ModeImport')]
class ModeImportDto
{
	#[OA\Property]
	public ?GameModeType $type = null;
	#[OA\Property(example: 'Team deathmach')]
	public string        $name;
	#[OA\Property]
	public string $description = '';
	#[OA\Property]
	public ?ModeSettings $settings = null;
	#[OA\Property]
	public bool $rankable = false;
	#[OA\Property]
	public int $order = 0;
}