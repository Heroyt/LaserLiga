<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Services\Achievements\CheckerInterface;

class AccuracyChecker implements CheckerInterface
{
	use ClassicModeOnly;

	public const MIN_SHOTS_PER_MINUTE = 3;

	public function check(Achievement $achievement, Game $game, Player $player): bool {
		return $this->checkClassic(
				$game
			) && $player->accuracy >= $achievement->value && ($player->shots / $game->getRealGameLength(
				)) >= $this::MIN_SHOTS_PER_MINUTE;
	}
}