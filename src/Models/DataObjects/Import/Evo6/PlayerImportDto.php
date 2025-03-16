<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import\Evo6;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Evo6PlayerImport')]
class PlayerImportDto extends \App\Models\DataObjects\Import\Lasermaxx\PlayerImportDto
{
	#[OA\Property(example: 10)]
	public ?int $bonuses  = null;
	#[OA\Property(example: 200)]
	public ?int $calories = null;
	#[OA\Property(example: 200)]
	public ?int $activity = null;
	#[OA\Property(example: 5)]
	public ?int $penaltyCount = null;
	#[OA\Property(example: -500)]
	public ?int $scorePenalty = null;
	#[OA\Property(example: 1000)]
	public ?int $scoreEncouragement = null;
	#[OA\Property(example: 1000)]
	public ?int $scoreActivity = null;
	#[OA\Property(example: 1000)]
	public ?int $scoreKnockout = null;
	#[OA\Property(example: 1000)]
	public ?int $scoreReality = null;
}