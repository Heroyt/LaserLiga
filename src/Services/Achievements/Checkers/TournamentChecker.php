<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Models\Achievements\AchievementType;
use App\Models\Auth\LigaPlayer;
use App\Services\Achievements\CheckerInterface;

class TournamentChecker implements CheckerInterface
{

	public function check(Achievement $achievement, Game $game, Player $player): bool {
		if ($game->getTournamentGame() === null) {
			return false;
		}

		$tournament = $game->getTournamentGame()->tournament;
		return match ($achievement->type) {
			AchievementType::TOURNAMENT_PLAY => count($player->user->getTournaments()) >= $achievement->value,
			AchievementType::TOURNAMENT_POSITION => $tournament->isFinished() && $this->getTournamentPosition(
					$game,
					$player->user
				) === $achievement->value,
			default => false,
		};
	}

	private function getTournamentPosition(Game $game, LigaPlayer $player): int {
		$tournament = $game->getTournamentGame()->tournament;

		$i = 1;
		foreach ($tournament->getSortedTeams() as $team) {
			foreach ($team->getPlayers() as $teamPlayer) {
				if ($teamPlayer->user?->id === $player->id) {
					return $i;
				}
			}
			++$i;
		}

		return 999999;
	}
}