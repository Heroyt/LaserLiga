<?php
declare(strict_types=1);

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Models\DataObjects\Player\PlayerAchievementRow;
use App\Services\Achievements\AchievementProvider;
use App\Services\Achievements\CheckerInterface;

readonly class BirthdayChecker implements CheckerInterface
{

	public function __construct(
		private AchievementProvider $achievementProvider
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function check(Achievement $achievement, Game $game, Player $player): bool {
		if ($player->user === null || $player->user->birthday === null) {
			return false;
		}

		// Check if the player's birthday is the game's date.
		if ($game->start->format('m-d') !== $player->user->birthday->format('m-d')) {
			return false;
		}

		// First ever birthday.
		if ($achievement->value < 2) {
			return true;
		}

		$previousAchievements = Achievement::query()
			->where('type = %s', $achievement->type)
			->where('value < %i', $achievement->value)
			->get();

		if (count($previousAchievements) < ($achievement->value-1)) {
			return false;
		}

		// Check if player has the previous achievement, and the year is different.
		$previousPlayerAchievements = $this->achievementProvider->queryAchievementsForUser($player->user)
			->where('id_achievement IN %in', array_map(static fn(Achievement $previousAchievement) => $previousAchievement->id, $previousAchievements))
			->fetchAllDto(PlayerAchievementRow::class);

		if (count($previousPlayerAchievements) !== count($previousAchievements)) {
			return false;
		}

		$year = $game->start->format('Y');
		$previousYears = array_map(static fn(PlayerAchievementRow $row) => $row->datetime->format('Y'), $previousPlayerAchievements);
		return !in_array($year, $previousYears, true);
	}
}