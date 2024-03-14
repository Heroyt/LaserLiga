<?php

namespace App\Services\GameHighlight\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Helpers\Gender;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\GameHighlightType;
use App\Models\DataObjects\Highlights\HighlightCollection;
use App\Services\GameHighlight\GameHighlightChecker;
use App\Services\GameHighlight\PlayerHighlightChecker;
use App\Services\GenderService;
use App\Services\NameInflectionService;

class HitsHighlightChecker implements GameHighlightChecker, PlayerHighlightChecker
{

	/**
	 * @inheritDoc
	 */
	public function checkGame(Game $game, HighlightCollection $highlights): void {
		$pairs = [];
		$maxHitsOwn = 0;
		/** @var Player[] $maxHitsOwnPlayers */
		$maxHitsOwnPlayers = [];
		$maxDeathsOwn = 0;
		/** @var Player[] $maxDeathsOwnPlayers */
		$maxDeathsOwnPlayers = [];
		foreach ($game->getPlayers()->getAll() as $player) {
			if ($maxHitsOwn <= $player->hitsOwn && $player->hitsOwn > 0) {
				if ($maxHitsOwn !== $player->hitsOwn) {
					$maxHitsOwn = $player->hitsOwn;
					$maxHitsOwnPlayers = [];
				}
				$maxHitsOwnPlayers[] = $player;
			}
			if ($maxDeathsOwn <= $player->deathsOwn && $player->deathsOwn > 0) {
				if ($maxDeathsOwn !== $player->deathsOwn) {
					$maxDeathsOwn = $player->deathsOwn;
					$maxDeathsOwnPlayers = [];
				}
				$maxDeathsOwnPlayers[] = $player;
			}

			$name1 = $player->name;
			foreach ($player->getHitsPlayers() as $hits) {
				if ($hits->count > 0 && $hits->count === $hits->playerTarget->getHitsPlayer($player)) {
					// Check for duplicate pairs (1-2 and 2-1 should be the same)
					$minId = min($player->vest, $hits->playerTarget->vest);
					$maxId = max($player->vest, $hits->playerTarget->vest);
					$key = $minId . '-' . $maxId;
					// Skip duplicates
					if (isset($pairs[$key])) {
						continue;
					}
					$pairs[$key] = true;
					$name2 = $hits->playerTarget->name;
					$highlights->add(
						new GameHighlight(
							GameHighlightType::HITS,
							sprintf(
								lang('Hráči %s a %s se oba navzájem zasáhli %dx.', context: 'results.highlights'),
								'@' . $name1 . '@',
								'@' . $name2 . '@',
								$hits->count
							),
							GameHighlight::MEDIUM_RARITY + ($hits->count * 2) // The more hits, the less rare -> higher score
						)
					);
				}
			}
		}

		if ($game->getMode()?->isTeam()) {
			$maxHitsOwnPlayerCount = count($maxHitsOwnPlayers);
			switch ($maxHitsOwnPlayerCount) {
				case 0:
					break;
				case 1:
					$gender = GenderService::rankWord($maxHitsOwnPlayers[0]->name);
					$highlights->add(
						new GameHighlight(
							GameHighlightType::HITS,
							sprintf(
								lang(
									match ($gender) {
										Gender::MALE   => '%s zasáhl nejvíce spoluhráčů (%d).',
										Gender::FEMALE => '%s zasáhla nejvíce spoluhráčů (%d).',
										Gender::OTHER  => '%s zasáhlo nejvíce spoluhráčů (%d).',
									},
									context: 'results.highlights'
								),
								'@' . $maxHitsOwnPlayers[0]->name . '@',
								$maxHitsOwn,
							),
							60 + ($maxHitsOwn * 5)
						)
					);
					break;
				default:
					$playerNames = array_map(static fn(Player $player) => '@' . $player->name . '@',
						$maxHitsOwnPlayers);
					$firstNames = implode(', ', array_slice($playerNames, 0, -1));
					$highlights->add(
						new GameHighlight(
							GameHighlightType::HITS,
							sprintf(
								lang('%s zasáhli nejvíce spoluhráčů (%d).'),
								$firstNames . ' ' . lang('a', context: 'spojka') . ' ' . last($playerNames),
								$maxHitsOwn,
							),
							60 + ($maxHitsOwn * 5)
						)
					);
					break;
			}

			$maxDeathsOwnPlayerCount = count($maxDeathsOwnPlayers);
			switch ($maxDeathsOwnPlayerCount) {
				case 0:
					break;
				case 1:
					$gender = GenderService::rankWord($maxDeathsOwnPlayers[0]->name);
					$highlights->add(
						new GameHighlight(
							GameHighlightType::HITS,
							sprintf(
								lang(
									match ($gender) {
										Gender::MALE   => '%s byl zasažen nejvíce spoluhráči (%d).',
										Gender::FEMALE => '%s byla zasažena nejvíce spoluhráči (%d).',
										Gender::OTHER  => '%s bylo zasaženo nejvíce spoluhráči (%d).',
									},
									context: 'results.highlights'
								),
								'@' . $maxDeathsOwnPlayers[0]->name . '@',
								$maxDeathsOwn,
							),
							60 + ($maxDeathsOwn * 5)
						)
					);
					break;
				default:
					$playerNames = array_map(static fn(Player $player) => '@' . $player->name . '@',
						$maxDeathsOwnPlayers);
					$firstNames = implode(', ', array_slice($playerNames, 0, -1));
					$highlights->add(
						new GameHighlight(
							GameHighlightType::HITS,
							sprintf(
								lang('%s byli zasaženi nejvíce spoluhráči (%d).', context: 'results.highlights'),
								$firstNames . ' ' . lang('a', context: 'spojka') . ' ' . last($playerNames),
								$maxDeathsOwn,
							),
							60 + ($maxDeathsOwn * 5)
						)
					);
					break;
			}
		}
	}

	public function checkPlayer(Player $player, HighlightCollection $highlights): void {
		if ($player->getGame()->getMode()?->isSolo()) {
			return;
		}
		$name1 = $player->name;
		$gender1 = GenderService::rankWord($name1);

		if ($player->hitsOwn > $player->hitsOther) {
			$highlights->add(
				new GameHighlight(
					GameHighlightType::HITS,
					sprintf(
						lang(
							match ($gender1) {
								Gender::MALE   => '%s zasáhl více spoluhráčů, než protihráčů',
								Gender::FEMALE => '%s zasáhla více spoluhráčů, než protihráčů',
								Gender::OTHER  => '%s zasáhlo více spoluhráčů, než protihráčů',
							},
							context: 'results.highlights'
						),
						'@' . $name1 . '@'
					),
					GameHighlight::VERY_HIGH_RARITY + 20
				)
			);
		}

		if ($player->getFavouriteTarget()?->getTeam()?->color === $player->getTeam()?->color) {
			$name2 = $player->getFavouriteTarget()?->name ?? '';
			$gender2 = GenderService::rankWord($name2);
			$name2Verb = match ($gender2) {
				Gender::OTHER, Gender::MALE => 'svého spoluhráče',
				Gender::FEMALE              => 'svou spoluhráčku',
			};
			$highlights->add(
				new GameHighlight(
					GameHighlightType::HITS,
					sprintf(
						lang(
							match ($gender1) {
								Gender::MALE   => '%s zasáhl ' . $name2Verb . ' %s vícekrát, než kteréhokoliv protihráče',
								Gender::FEMALE => '%s zasáhla ' . $name2Verb . ' %s vícekrát, než kteréhokoliv protihráče',
								Gender::OTHER  => '%s zasáhlo ' . $name2Verb . ' %s vícekrát, než kteréhokoliv protihráče',
							},
							context: 'results.highlights'
						),
						'@' . $name1 . '@',
						'@' . $name2 . '@<' . NameInflectionService::accusative($name2) . '>'
					),
					GameHighlight::VERY_HIGH_RARITY + 20
				)
			);
		}
	}
}