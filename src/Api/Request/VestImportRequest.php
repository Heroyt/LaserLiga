<?php
declare(strict_types=1);

namespace App\Api\Request;

use App\Models\DataObjects\Import\VestImportDto;
use Lsr\ObjectValidation\Attributes\Required;
use OpenApi\Attributes as OA;

#[OA\Schema]
class VestImportRequest
{

	/** @var VestImportDto[] */
	#[Required, OA\Property(type: 'array', items: new OA\Items(ref: "#/components/schemas/VestImport"))]
	public array $vest;

	public function addVest(VestImportDto $vest): void {
		$this->vest[] = $vest;
	}

}