<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Services\Achievements\CheckerInterface;
use Lsr\Core\DB;

class GamesPerDayChecker implements CheckerInterface
{
	use WithPlayerGameSelect;

	public function check(Achievement $achievement, Game $game, Player $player): bool {
		// Get today's game count
		$gameCount = $this->selectPlayerWithGames('COUNT(*)', $game, $player)
		                  ->where('DATE(g.start) = %d AND g.start <= %dt', $game->start, $game->start)
		                  ->fetchSingle();
		return $gameCount >= $achievement->value;
	}
}