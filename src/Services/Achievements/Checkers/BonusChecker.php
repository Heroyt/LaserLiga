<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Models\Achievements\AchievementType;
use App\Services\Achievements\CheckerInterface;
use Exception;

class BonusChecker implements CheckerInterface
{

	/**
	 * @param Achievement $achievement
	 * @param Game        $game
	 * @param Player      $player
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function check(Achievement $achievement, Game $game, Player $player): bool {
		if (!$player instanceof \App\GameModels\Game\Evo5\Player) {
			return false;
		}
		return match ($achievement->type) {
			AchievementType::BONUS              => $player->bonus->getSum() >= $achievement->value,
			AchievementType::BONUS_SHIELD       => $player->bonus->shield >= $achievement->value,
			AchievementType::BONUS_MACHINE_GUN  => $player->bonus->machineGun >= $achievement->value,
			AchievementType::BONUS_INVISIBILITY => $player->bonus->invisibility >= $achievement->value,
			AchievementType::BONUS_SPY          => $player->bonus->agent >= $achievement->value,
			default                             => false,
		};
	}
}