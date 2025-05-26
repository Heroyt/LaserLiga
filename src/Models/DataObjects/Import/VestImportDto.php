<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import;

use App\Models\SystemType;
use Lsr\LaserLiga\Enums\VestStatus;
use Lsr\ObjectValidation\Attributes\Required;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'VestImport')]
class VestImportDto
{

	#[Required, OA\Property]
	public string $vestNum;

	#[Required, OA\Property]
	public SystemType|SystemImportDto $system;

	#[OA\Property]
	public VestStatus $status = VestStatus::OK;

	#[OA\Property]
	public ?string $info = null;

}