<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Services\Achievements\CheckerInterface;
use App\Services\Player\PlayerStatsProvider;

class GameCountChecker implements CheckerInterface
{

	public function __construct(
		private readonly PlayerStatsProvider $statsProvider
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function check(Achievement $achievement, Game $game, Player $player): bool {
		return $this->statsProvider->calculatePlayerGamesPlayed($player->user, $game->start) >= $achievement->value;
	}
}