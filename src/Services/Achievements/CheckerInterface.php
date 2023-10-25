<?php

namespace App\Services\Achievements;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;

interface CheckerInterface
{

	/**
	 * Check if the achievement should be obtained by the player
	 *
	 * @param Achievement $achievement
	 * @param Game        $game
	 * @param Player      $player
	 *
	 * @return bool
	 */
	public function check(Achievement $achievement, Game $game, Player $player): bool;

}