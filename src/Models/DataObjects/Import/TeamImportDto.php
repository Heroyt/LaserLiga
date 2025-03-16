<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'TeamImport')]
class TeamImportDto
{
	#[OA\Property(example: 1)]
	public ?int    $id             = null;
	#[OA\Property(example: 1)]
	public ?int    $id_team        = null;
	#[OA\Property(example: 'Modrý tým')]
	public ?string $name           = null;
	#[OA\Property(example: 10000)]
	public ?int    $score          = null;
	#[OA\Property(example: 1)]
	public ?int    $color          = null;
	#[OA\Property(example: 1)]
	public ?int    $position       = null;
	#[OA\Property(example: 1)]
	public ?int    $tournamentTeam = null;
	#[OA\Property(example: 500)]
	public ?int $bonus = null;
}