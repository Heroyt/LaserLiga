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
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
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

	public function checkPlayer(Player $player, HighlightCollection $highlights): void {
		if (!isset($player->user)) {
			return;
		}

		$this->checkPlayerAccuracy($player, $highlights);

		$this->checkPlayerShots($player, $player->getGame(), $highlights);

		try {
			$this->checkComparePlayerHighlights($player->getGame(), $player, $highlights);
		} catch (Throwable) {
		}
	}

	/**
	 * Checks the player's accuracy and adds a game highlight if the accuracy is better or worse than usual.
	 *
	 * @param Player              $player     The player object.
	 * @param HighlightCollection $highlights The highlight collection object.
	 *
	 * @return void
	 */
	private function checkPlayerAccuracy(Player $player, HighlightCollection $highlights): void {
		$accuracyDiff = $player->accuracy / $player->user->stats->averageAccuracy;
		if ($accuracyDiff > $this->minThreshold) {
			$highlights->add(
				new GameHighlight(
					GameHighlightType::USER_AVERAGE,
					sprintf(
						lang(
							         '%s má %0.2fx lepší přesnost, než obvykle',
							context: 'results.highlights'
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
							context: 'results.highlights'
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
	 *
	 * @param Player              $player     The player whose shots need to be checked.
	 * @param Game                $game       The game in which the player participated.
	 * @param HighlightCollection $highlights The collection of highlights to add to.
	 *
	 * @return void
	 */
	private function checkPlayerShots(Player $player, Game $game, HighlightCollection $highlights): void {
		$shotsDiff = ($player->shots / $game->getRealGameLength()) / $player->user->stats->averageShotsPerMinute;
		if ($shotsDiff > $this->minThreshold) {
			$highlights->add(
				new GameHighlight(
					GameHighlightType::USER_AVERAGE,
					sprintf(
						lang(
							         '%s má %0.2fx více výstřelů za minutu, než obvykle',
							context: 'results.highlights'
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
							context: 'results.highlights'
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
	 * @param Game                $game       The game object.
	 * @param Player              $player     The player to compare.
	 * @param HighlightCollection $highlights The collection to store highlights.
	 *
	 * @return void
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws Throwable
	 */
	private function checkComparePlayerHighlights(Game $game, Player $player, HighlightCollection $highlights): void {
		$gender = GenderService::rankWord($player->name);
		// Find registered players other than player1 and compare their regular results with this game
		foreach ($game->getPlayers()->getAll() as $player2) {
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
								context: 'results.highlights'
							),
							'@' . $player->name . '@',
							'@' . $player2->name . '@',
						),
						GameHighlight::MEDIUM_RARITY
					)
				);
			}

			// Skip players that are not registered, or if the player2 is the same as player1 or if
			if (!isset($player2->user) || ($game->getMode()?->isTeam() && $player->getTeam(
					)?->color === $player2->getTeam()?->color)) {
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
				if ($winTeam?->color === $player->getTeam()?->color) {
					$isWin = true;
				}
				else if ($winTeam?->color !== $player2->getTeam()?->color) {
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
	 *
	 * @param Game                $game          The game object.
	 * @param GamesTogether       $gamesTogether The gamesTogether object.
	 * @param Player              $player        The player to check win ratio against.
	 * @param Gender              $gender        The gender of the player.
	 * @param Player              $player2       The player to compare win ratio with.
	 * @param HighlightCollection $highlights    The collection to store highlights.
	 *
	 * @return void
	 */
	private function checkPlayerWinRatioAgainstAnotherPlayer(Game $game, GamesTogether $gamesTogether, Player $player, Gender $gender, Player $player2, HighlightCollection $highlights): void {
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
						context: 'results.highlights'
					),
					'@' . $player->name . '@',
					'@' . $player2->name . '@<' . NameInflectionService::dative(
						$player2->name
					) . '>',
				) . ($game->getMode()?->isTeam() ? ' (' . lang(
						         'Týmové skóre',
						context: 'results.highlights'
					) . ')' : ''),
				(int)round(
					GameHighlight::MEDIUM_RARITY + (20 / $winLossRatio)
				)
			)
		);
	}

}