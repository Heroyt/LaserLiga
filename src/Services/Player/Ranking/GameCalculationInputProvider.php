<?php
declare(strict_types=1);

namespace App\Services\Player\Ranking;

use App\Models\DataObjects\Ranking\Calculation\GameCalculationInput;

interface GameCalculationInputProvider
{

	public function getByCode(string $code): ?GameCalculationInput;

	/**
	 * @return iterable<GameCalculationInput>
	 */
	public function iterateRankableGames(GameSelection $selection): iterable;

}
