<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Services\Achievements\CheckerInterface;

class DeathsChecker implements CheckerInterface
{

	use ClassicModeOnly;

	public function check(Achievement $achievement, Game $game, Player $player): bool {
		return $this->checkClassic($game) && $player->deaths > 0 && ($player->deaths / $game->getRealGameLength(
				)) < (float)$achievement->value;
	}
}