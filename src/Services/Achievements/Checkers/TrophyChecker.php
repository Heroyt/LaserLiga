<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Services\Achievements\CheckerInterface;

class TrophyChecker implements CheckerInterface
{

	use ClassicModeOnly;

	public function check(Achievement $achievement, Game $game, Player $player): bool {
		if (!$this->checkClassic($game)) {
			return false;
		}

		$trophies = $player->user?->getTrophyCount(true, $game->start) ?? [];

		return ($trophies[$achievement->key] ?? 0) >= $achievement->value;
	}
}