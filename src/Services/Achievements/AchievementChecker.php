<?php

namespace App\Services\Achievements;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\AchievementType;
use App\Models\Achievements\PlayerAchievement;
use App\Services\Achievements\Checkers\AccuracyChecker;
use App\Services\Achievements\Checkers\ArenasChecker;
use App\Services\Achievements\Checkers\AutoChecker;
use App\Services\Achievements\Checkers\BirthdayChecker;
use App\Services\Achievements\Checkers\BonusChecker;
use App\Services\Achievements\Checkers\DeathsChecker;
use App\Services\Achievements\Checkers\GameCountChecker;
use App\Services\Achievements\Checkers\GamesDaySuccessiveChecker;
use App\Services\Achievements\Checkers\GamesPerDayChecker;
use App\Services\Achievements\Checkers\GamesPerMonthChecker;
use App\Services\Achievements\Checkers\HitsChecker;
use App\Services\Achievements\Checkers\KDChecker;
use App\Services\Achievements\Checkers\PositionChecker;
use App\Services\Achievements\Checkers\ShotsMaxChecker;
use App\Services\Achievements\Checkers\ShotsMinChecker;
use App\Services\Achievements\Checkers\TournamentChecker;
use App\Services\Achievements\Checkers\TrophyChecker;
use App\Services\Player\PlayerUserService;
use DateTimeImmutable;
use Exception;

readonly class AchievementChecker
{

	public function __construct(
		private AchievementProvider       $achievementProvider,
		private AutoChecker               $autoChecker,
		private GameCountChecker          $gameCountChecker,
		private GamesPerDayChecker        $gamesPerDayChecker,
		private AccuracyChecker           $accuracyChecker,
		private ArenasChecker             $arenasChecker,
		private PositionChecker           $positionChecker,
		private HitsChecker               $hitsChecker,
		private DeathsChecker             $deathsChecker,
		private KDChecker                 $kdChecker,
		private ShotsMinChecker           $shotsMinChecker,
		private ShotsMaxChecker           $shotsMaxChecker,
		private GamesDaySuccessiveChecker $gamesDaySuccessiveChecker,
		private GamesPerMonthChecker      $gamesPerMonthChecker,
		private BonusChecker              $bonusChecker,
		private TrophyChecker             $trophyChecker,
		private TournamentChecker         $tournamentChecker,
		private BirthdayChecker $birthdayChecker,
	) {
	}

	/**
	 * @param Game $game
	 *
	 * @pre Stats from the game should already be processed
	 * @return PlayerAchievement[] New achievements that were not yet saved
	 * @see PlayerUserService::updatePlayerStats()
	 */
	public function checkGame(Game $game): array {
		$achievements = [];
		foreach ($game->getPlayers() as $player) {
			if (isset($player->user)) {
				$achievements[] = $this->checkPlayerGame($game, $player);
			}
		}
		return array_merge(...$achievements);
	}

	/**
	 * @param Game   $game
	 * @param Player $player
	 *
	 * @pre Stats from the game should already be processed
	 * @return PlayerAchievement[]
	 * @see PlayerUserService::updatePlayerStats()
	 */
	public function checkPlayerGame(Game $game, Player $player): array {
		if (!isset($player->user)) {
			return [];
		}
		$achievements = [];

		// Get unclaimed achievements
		$unclaimed = $this->achievementProvider->getUnclaimedByUser($player->user);
		foreach ($unclaimed as $achievement) {
			// Get the correct checker
			$checker = $this->getCheckerForType($achievement->type);
			if ($checker->check($achievement, $game, $player)) {
				$achievements[] = new PlayerAchievement(
					$achievement, $player->user, $game, $game->end ?? $game->start ?? new DateTimeImmutable
				);
			}
		}

		return $achievements;
	}

	/**
	 * Get the correct checker for achievement type
	 *
	 * @param AchievementType $type
	 *
	 * @return CheckerInterface
	 */
	private function getCheckerForType(AchievementType $type): CheckerInterface {
		return match ($type) {
			AchievementType::GAME_COUNT           => $this->gameCountChecker,
			AchievementType::GAMES_PER_DAY        => $this->gamesPerDayChecker,
			AchievementType::ACCURACY             => $this->accuracyChecker,
			AchievementType::ARENAS               => $this->arenasChecker,
			AchievementType::POSITION             => $this->positionChecker,
			AchievementType::HITS                 => $this->hitsChecker,
			AchievementType::DEATHS               => $this->deathsChecker,
			AchievementType::KD                   => $this->kdChecker,
			AchievementType::SHOTS_MIN            => $this->shotsMinChecker,
			AchievementType::SHOTS_MAX            => $this->shotsMaxChecker,
			AchievementType::GAME_DAYS_SUCCESSIVE => $this->gamesDaySuccessiveChecker,
			AchievementType::GAMES_PER_MONTH      => $this->gamesPerMonthChecker,
			AchievementType::SIGNUP               => $this->autoChecker,
			AchievementType::TOURNAMENT_PLAY,
			AchievementType::TOURNAMENT_POSITION  => $this->tournamentChecker,
			AchievementType::LEAGUE_POSITION      => throw new Exception('To be implemented'),
			AchievementType::BONUS,
			AchievementType::BONUS_MACHINE_GUN,
			AchievementType::BONUS_SHIELD,
			AchievementType::BONUS_INVISIBILITY,
			AchievementType::BONUS_SPY            => $this->bonusChecker,
			AchievementType::TROPHY               => $this->trophyChecker,
			AchievementType::BIRTHDAY             => $this->birthdayChecker,
		};
	}

}