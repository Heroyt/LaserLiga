<?php

namespace App\Services\GameHighlight;

use App\GameModels\Game\Game;
use App\GameModels\Game\Team;
use App\Helpers\Gender;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\GameHighlightType;
use App\Models\DataObjects\Highlights\HighlightCollection;
use App\Services\GenderService;
use App\Services\NameInflectionService;
use App\Services\Player\PlayersGamesTogetherService;

class UserHighlightChecker implements GameHighlightChecker
{

	/** @var float Percentage difference from the average where the highlight should be considered */
	public const HIGHLIGHT_THRESHOLD = 0.3;

	public function __construct(
		private readonly PlayersGamesTogetherService $gamesTogetherService
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function checkGame(Game $game, HighlightCollection $highlights): void {
		foreach ($game->getPlayers()->getAll() as $player) {
			if (!isset($player->user)) {
				continue;
			}

			$gender = GenderService::rankWord($player->name);

			$minThreshold = 1 + self::HIGHLIGHT_THRESHOLD;
			$maxThreshold = 1 / $minThreshold;

			$accuracyDiff = $player->accuracy / $player->user->stats->averageAccuracy;
			if ($accuracyDiff > $minThreshold) {
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
						(int)round(GameHighlight::MEDIUM_RARITY + (5 * $accuracyDiff))
					)
				);
			}
			else if ((int)$accuracyDiff !== 0 && $accuracyDiff < $maxThreshold) {
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
						(int)round(GameHighlight::MEDIUM_RARITY + (5 / $accuracyDiff))
					)
				);
			}

			$shotsDiff = ($player->shots / $game->getRealGameLength()) / $player->user->stats->averageShotsPerMinute;
			if ($shotsDiff > $minThreshold) {
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
						(int)round(GameHighlight::MEDIUM_RARITY + (5 * $shotsDiff))
					)
				);
			}
			else if ($shotsDiff < $maxThreshold) {
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
						(int)round(GameHighlight::MEDIUM_RARITY + (5 / $shotsDiff))
					)
				);
			}

			foreach ($game->getPlayers()->getAll() as $player2) {
				if (
					!isset($player2->user) ||
					$player->vest === $player2->vest ||
					($game->mode?->isTeam() && $player->getTeam()?->color === $player2->getTeam()?->color)
				) {
					continue;
				}

				$gamesTogether = $this->gamesTogetherService->getGamesTogether($player->user, $player2->user);
				if ($gamesTogether->gameCountEnemy < 5) {
					continue; // Skip if players did not play enough games together
				}

				// Detect win, draw and loss
				$isWin = false;
				$isDraw = false;
				if ($game->mode?->isTeam()) {
					/** @var Team|null $winTeam */
					$winTeam = $game->mode?->getWin($game);
					if ($winTeam?->color === $player->getTeam()?->color) {
						$isWin = true;
					}
					else if ($winTeam?->color !== $player2->getTeam()?->color) {
						$isDraw = true;
					}
				}
				else if ($player->score === $player2->score) {
					$isDraw = true;
				}
				else if ($player->score > $player2->score) {
					$isWin = true;
				}


				if ($isDraw) {
					if ($game->mode->isSolo()) {
						$highlights->add(
							new GameHighlight(
								GameHighlightType::OTHER,
								sprintf(
									lang('%s a %s mají stejné skóre.', context: 'results.highlights'),
									'@' . $player->name . '@',
									'@' . $player2->name . '@',
								),
								GameHighlight::MEDIUM_RARITY
							)
						);
					}
					continue; // We don't care about team draws
				}

				$gender2 = GenderService::rankWord($player2->name);
				$genderedWord = match ($gender2) {
					Gender::MALE   => 'ho',
					Gender::FEMALE => 'jí',
					Gender::OTHER  => 'to',
				};

				$losses = $gamesTogether->getLossesEnemyForPlayer($player->user);
				if ($losses > 0) {
					$winLossRatio = $gamesTogether->getWinsEnemyForPlayer(
							$player->user
						) / $losses;
					if ($isWin && $winLossRatio <= $maxThreshold) {
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
									'@' . $player2->name . '@<' . NameInflectionService::dative($player2->name) . '>',
								),
								(int)round(GameHighlight::MEDIUM_RARITY + (20 / $accuracyDiff))
							)
						);
					}
				}
			}
		}
	}
}