<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;

trait ClassicModeOnly
{

	public function checkClassic(Game $game): bool {
		return $game->getMode()->rankable;
	}

}