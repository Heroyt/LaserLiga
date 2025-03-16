<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'GroupImport')]
class GroupImportDto
{
	#[OA\Property(description: 'Local group ID', example: 1)]
	public int    $id;
	#[OA\Property(example: 'Skupina ABC')]
	public string $name;
}