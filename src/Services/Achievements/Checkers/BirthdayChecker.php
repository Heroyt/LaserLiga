<?php
declare(strict_types=1);

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Services\Achievements\CheckerInterface;

class BirthdayChecker implements CheckerInterface
{

	/**
	 * @inheritDoc
	 */
	public function check(Achievement $achievement, Game $game, Player $player): bool {
		if ($player->user === null || $player->user->birthday === null) {
			return false;
		}

		return $game->start->format('m-d') === $player->user->birthday->format('m-d');
	}
}