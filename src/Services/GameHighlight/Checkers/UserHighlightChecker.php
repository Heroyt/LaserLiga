<?php

namespace App\Services\GameHighlight\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Helpers\Gender;
use App\Models\DataObjects\GamesTogether;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\GameHighlightType;
use App\Models\DataObjects\Highlights\HighlightCollection;
use App\Services\GameHighlight\PlayerHighlightChecker;
use App\Services\GenderService;
use App\Services\NameInflectionService;
use App\Services\Player\PlayersGamesTogetherService;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\PlayerInterface;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Throwable;

class UserHighlightChecker implements PlayerHighlightChecker
{

	/** @var float Percentage difference from the average where the highlight should be considered */
	public const float HIGHLIGHT_THRESHOLD = 0.3;
	private float $minThreshold;
	private float $maxThreshold;

	public function __construct(private readonly PlayersGamesTogetherService $gamesTogetherService) {
		$this->minThreshold = 1 + self::HIGHLIGHT_THRESHOLD;
		$this->maxThreshold = 1 / $this->minThreshold;
	}

	public function checkPlayer(PlayerInterface $player, HighlightCollection $highlights): void {
		if (!isset($player->user)) {
			return;
		}

		$this->checkPlayerAccuracy($player, $highlights);

		$this->checkPlayerShots($player, $player->game, $highlights);

		try {
			$this->checkComparePlayerHighlights($player->game, $player, $highlights);
		} catch (Throwable) {
		}
	}

	/**
	 * Checks the player's accuracy and adds a game highlight if the accuracy is better or worse than usual.
	 */
	private function checkPlayerAccuracy(PlayerInterface $player, HighlightCollection $highlights): void {
		assert($player instanceof Player);
		if ($player->user->stats->averageAccuracy < 1) {
			return;
		}
		$accuracyDiff = $player->accuracy / $player->user->stats->averageAccuracy;
		if ($accuracyDiff > $this->minThreshold) {
			$highlights->add(
				new GameHighlight(
					GameHighlightType::USER_AVERAGE,
					sprintf(
						lang(
							         '%s má %0.2fx lepší přesnost, než obvykle',
							context: 'user',
							domain : 'highlights'
						),
						'@' . $player->name . '@',
						$accuracyDiff
					),
					(int)round(
						GameHighlight::MEDIUM_RARITY + (5 * $accuracyDiff)
					)
				)
			);
		}
		else if ((int)$accuracyDiff !== 0 && $accuracyDiff < $this->maxThreshold) {
			$highlights->add(
				new GameHighlight(
					GameHighlightType::USER_AVERAGE,
					sprintf(
						lang(
							         '%s má %0.2fx horší přesnost, než obvykle',
							context: 'user',
							domain : 'highlights'
						),
						'@' . $player->name . '@',
						1 / $accuracyDiff
					),
					(int)round(
						GameHighlight::MEDIUM_RARITY + (5 / $accuracyDiff)
					)
				)
			);
		}
	}

	/**
	 * Checks the player's shots in the game and adds highlights if necessary.
	 */
	private function checkPlayerShots(PlayerInterface $player, GameInterface $game, HighlightCollection $highlights): void {
		assert($player instanceof Player && $game instanceof Game);
		$shotsDiff = ($player->shots / $game->getRealGameLength()) / $player->user->stats->averageShotsPerMinute;
		if ($shotsDiff > $this->minThreshold) {
			$highlights->add(
				new GameHighlight(
					GameHighlightType::USER_AVERAGE,
					sprintf(
						lang(
							         '%s má %0.2fx více výstřelů za minutu, než obvykle',
							context: 'user',
							domain : 'highlights'
						),
						'@' . $player->name . '@',
						$shotsDiff
					),
					(int)round(
						GameHighlight::MEDIUM_RARITY + (5 * $shotsDiff)
					)
				)
			);
		}
		else if ($shotsDiff < $this->maxThreshold && ((int)$shotsDiff) !== 0) {
			$highlights->add(
				new GameHighlight(
					GameHighlightType::USER_AVERAGE,
					sprintf(
						lang(
							         '%s má %0.2fx méně výstřelů za minutu, než obvykle',
							context: 'user',
							domain : 'highlights'
						),
						'@' . $player->name . '@',
						1 / $shotsDiff
					),
					(int)round(
						GameHighlight::MEDIUM_RARITY + (5 / $shotsDiff)
					)
				)
			);
		}
	}

	/**
	 * Checks for player highlights in comparison with other players in a game.
	 *
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws Throwable
	 */
	private function checkComparePlayerHighlights(GameInterface $game, PlayerInterface $player, HighlightCollection $highlights): void {
		assert($player instanceof Player && $game instanceof Game);
		$gender = GenderService::rankWord($player->name);
		// Find registered players other than player1 and compare their regular results with this game
		/** @var Player $player2 */
		foreach ($game->players->getAll() as $player2) {
			// Skip if the players are the same
			if ($player->vest === $player2->vest) {
				continue;
			}

			// Check if the score is the same
			if (($player->score === $player2->score)) {
				$highlights->add(
					new GameHighlight(
						GameHighlightType::OTHER,
						sprintf(
							lang(
								         '%s a %s mají stejné skóre.',
								context: 'user',
								domain : 'highlights'
							),
							'@' . $player->name . '@',
							'@' . $player2->name . '@',
						),
						GameHighlight::MEDIUM_RARITY
					)
				);
			}

			// Skip players that are not registered, or if the player2 is the same as player1 or if
			if (!isset($player2->user) || ($game->mode->isTeam() && $player->team?->color === $player2->team?->color)) {
				continue;
			}

			$gamesTogether = $this->gamesTogetherService->getGamesTogether($player->user, $player2->user);
			if ($gamesTogether->gameCountEnemy < 5) {
				continue; // Skip if players did not play enough games together
			}

			// Detect win, draw and loss
			$isWin = false;
			if ($game->getMode()?->isTeam()) {
				/** @var Team|null $winTeam */
				$winTeam = $game->getMode()->getWin($game);
				if ($winTeam?->color === $player->team?->color) {
					$isWin = true;
				}
				else if ($winTeam?->color !== $player2->team?->color) {
					continue; // We don't care for draws
				}
			}
			else if ($player->score === $player2->score) {
				continue; // We don't care for draws
			}
			else if ($player->score > $player2->score) {
				$isWin = true;
			}

			if ($isWin) {
				$this->checkPlayerWinRatioAgainstAnotherPlayer(
					$game,
					$gamesTogether,
					$player,
					$gender,
					$player2,
					$highlights,
				);
			}
		}
	}

	/**
	 * Checks the player's win ratio against another player in a game.
	 */
	private function checkPlayerWinRatioAgainstAnotherPlayer(GameInterface $game, GamesTogether $gamesTogether, PlayerInterface $player, Gender $gender, PlayerInterface $player2, HighlightCollection $highlights): void {
		assert($player instanceof Player && $player2 instanceof Player);
		if (!isset($player->user)) {
			return;
		}

		$gender2 = GenderService::rankWord($player2->name);
		$genderedWord = match ($gender2) {
			Gender::MALE   => 'ho',
			Gender::FEMALE => 'jí',
			Gender::OTHER  => 'to',
		};

		$losses = $gamesTogether->getLossesEnemyForPlayer($player->user);
		if ($losses <= 0) {
			return;
		}
		$winLossRatio = $gamesTogether->getWinsEnemyForPlayer($player->user) / $losses;
		if ($winLossRatio > $this->maxThreshold) {
			return;
		}
		$highlights->add(
			new GameHighlight(
				GameHighlightType::OTHER,
				sprintf(
					lang(
						match ($gender) {
							Gender::MALE   => '%s proti %s většinou prohrává, ale teď ' . $genderedWord . ' porazil.',
							Gender::FEMALE => '%s proti %s většinou prohrává, ale teď ' . $genderedWord . ' porazila.',
							Gender::OTHER  => '%s proti %s většinou prohrává, ale teď ' . $genderedWord . ' porazilo.',
						},
						context: 'user',
						domain : 'highlights'
					),
					'@' . $player->name . '@',
					'@' . $player2->name . '@<' . NameInflectionService::dative(
						$player2->name
					) . '>',
				) . (
				$game->mode?->isTeam() ?
					' (' . lang('Týmové skóre', context: 'user', domain: 'highlights') . ')'
					: ''
				),
				(int)round(
					GameHighlight::MEDIUM_RARITY + (20 / $winLossRatio)
				)
			)
		);
	}

}