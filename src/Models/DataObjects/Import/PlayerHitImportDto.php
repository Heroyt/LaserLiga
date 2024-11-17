<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'PlayerHitImport')]
class PlayerHitImportDto
{
	#[OA\Property(description: 'Target player\'s ID', example: '2')]
	public int $target;
	#[OA\Property(example: 10)]
	public int $count;
}