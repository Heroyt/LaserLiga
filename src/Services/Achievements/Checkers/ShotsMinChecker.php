<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Services\Achievements\CheckerInterface;

class ShotsMinChecker implements CheckerInterface
{

	public function check(Achievement $achievement, Game $game, Player $player): bool {
		return $player->shots > 0 && $player->shots / $game->getRealGameLength() <= $achievement->value;
	}
}