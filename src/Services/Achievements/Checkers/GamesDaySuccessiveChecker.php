<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Services\Achievements\CheckerInterface;
use DateTimeImmutable;

class GamesDaySuccessiveChecker implements CheckerInterface
{
	use WithPlayerGameSelect;

	public function check(Achievement $achievement, Game $game, Player $player): bool {
		$dateFrom = new DateTimeImmutable($game->start->format('Y-m-d') . ' -' . $achievement->value . ' days');
		$query = $this->selectPlayerWithGames('DATE(g.start) as [date]', $game, $player)->where(
			'DATE(g.start) >= %d AND DATE(start) <= %d',
			$dateFrom,
			$game->start,
		)
		              ->groupBy('date');
		return $query->count() === $achievement->value;
	}
}