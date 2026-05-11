<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Ranking\Calculation;

final readonly class PlayerCalculationInput
{

	public function __construct(
		public int    $playerId,
		public ?int   $userId,
		public ?int   $teamId,
		public string $name,
		public int    $score,
		public int    $skill,
		public int    $shots,
		public int    $accuracy,
		public int    $hits,
		public int    $deaths,
		public int    $position,
	) {
	}

}
