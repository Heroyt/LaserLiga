<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'TeamColorImport')]
class TeamColorImportDto
{
	#[OA\Property(example: 1)]
	public ?int $id = null;
	#[OA\Property(example: 1)]
	public int $color;
}