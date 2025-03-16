<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import\Lasermaxx;

use OpenApi\Attributes as OA;

class GameImportDto extends \App\Models\DataObjects\Import\GameImportDto
{

	#[OA\Property]
	public bool $blastShots = false;

	#[OA\Property]
	public int $reloadClips = 0;

	#[OA\Property]
	public bool $allowFriendlyFire = true;
	#[OA\Property]
	public bool $antiStalking = false;

}