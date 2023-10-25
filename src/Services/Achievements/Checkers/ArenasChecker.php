<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Services\Achievements\CheckerInterface;

class ArenasChecker implements CheckerInterface
{

	public function check(Achievement $achievement, Game $game, Player $player): bool {
		return $player->user?->stats->arenasPlayed >= $achievement->value;
	}
}