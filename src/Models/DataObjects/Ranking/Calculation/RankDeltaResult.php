<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Ranking\Calculation;

use DateTimeInterface;

final readonly class RankDeltaResult
{

	public function __construct(
		public string            $gameCode,
		public int               $userId,
		public DateTimeInterface $date,
		public float             $difference,
		public ?float            $normalizedSkill,
		public int|float         $minSkill,
		public int|float         $maxSkill,
		public array             $debug,
	) {
	}

}
