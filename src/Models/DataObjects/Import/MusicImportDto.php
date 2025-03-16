<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'MusicImport')]
class MusicImportDto
{
	#[OA\Property(description: 'Local music mode ID', example: 1)]
	public int $id;
}