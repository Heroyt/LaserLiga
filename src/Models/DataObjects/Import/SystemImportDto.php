<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import;

use App\Models\SystemType;
use Lsr\ObjectValidation\Attributes\Required;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'SystemImport')]
class SystemImportDto
{

	#[Required, OA\Property]
	public string $name;
	#[Required, OA\Property]
	public SystemType $type;
	#[OA\Property]
	public bool $active = true;
	#[OA\Property]
	public bool $default = false;

}