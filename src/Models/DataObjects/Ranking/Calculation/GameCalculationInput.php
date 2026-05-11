<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Ranking\Calculation;

use DateTimeInterface;

final readonly class GameCalculationInput
{

	/**
	 * @param PlayerCalculationInput[] $players
	 */
	public function __construct(
		public string            $code,
		public string            $system,
		public int               $gameId,
		public ?int              $modeId,
		public bool              $rankable,
		public bool              $solo,
		public DateTimeInterface $startedAt,
		public array             $players,
	) {
	}

}
