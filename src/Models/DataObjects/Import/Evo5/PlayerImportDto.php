<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import\Evo5;

use Lsr\Lg\Results\LaserMaxx\Evo5\BonusCounts;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Evo5PlayerImport')]
class PlayerImportDto extends \App\Models\DataObjects\Import\Lasermaxx\PlayerImportDto
{
	#[OA\Property]
	public ?BonusCounts $bonus = null;
}