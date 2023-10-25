<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Services\Achievements\CheckerInterface;

class GamesPerMonthChecker implements CheckerInterface
{
	use WithPlayerGameSelect;

	public function check(Achievement $achievement, Game $game, Player $player): bool {
		$count = $this->selectPlayerWithGames('COUNT(*)', $game, $player)->where(
			'DATE(g.start) BETWEEN %d AND %d',
			strtotime(
				$game->start->format('Y-m-1')
			),
			$game->start
		)->fetchSingle();
		return $count >= $achievement->value;
	}
}