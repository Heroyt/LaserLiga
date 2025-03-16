<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import\Lasermaxx;

use OpenApi\Attributes as OA;

class PlayerImportDto extends \App\Models\DataObjects\Import\PlayerImportDto
{
	#[OA\Property(example: 128)]
	public ?int $scoreAccuracy = null;
	#[OA\Property(example: 500)]
	public ?int $scoreVip = null;
	#[OA\Property(example: 2)]
	public ?int $minesHits = null;
}