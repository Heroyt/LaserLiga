<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Player;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'PlayerRadarData', type: 'object')]
readonly class PlayerRadarData
{
	public function __construct(
		#[OA\Property]
		public PlayerRadarValue $rank,
		#[OA\Property]
		public PlayerRadarValue $shotsPerMinute,
		#[OA\Property]
		public PlayerRadarValue $accuracy,
		#[OA\Property]
		public PlayerRadarValue $hits,
		#[OA\Property]
		public PlayerRadarValue $deaths,
		#[OA\Property]
		public PlayerRadarValue $kd,
	) {
	}
}