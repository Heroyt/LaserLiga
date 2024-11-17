<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import\Evo6;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Evo6PlayerImport')]
class PlayerImportDto extends \App\Models\DataObjects\Import\PlayerImportDto
{
	#[OA\Property(example: 10)]
	public ?int $bonuses = null;
	#[OA\Property(example: 200)]
	public ?int $calories = null;
}