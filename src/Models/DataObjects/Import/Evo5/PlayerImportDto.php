<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import\Evo5;

use App\GameModels\Game\Evo5\BonusCounts;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Evo5PlayerImport')]
class PlayerImportDto extends \App\Models\DataObjects\Import\PlayerImportDto
{
	#[OA\Property]
	public ?BonusCounts $bonus = null;
}