<?php

namespace App\Services\GameHighlight\Checkers;

use App\GameModels\Game\Player;
use App\Helpers\Gender;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\GameHighlightType;
use App\Models\DataObjects\Highlights\HighlightCollection;
use App\Services\GameHighlight\GameHighlightChecker;
use App\Services\GameHighlight\PlayerHighlightChecker;
use App\Services\GenderService;
use App\Services\NameInflectionService;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\PlayerInterface;

class HitsHighlightChecker implements GameHighlightChecker, PlayerHighlightChecker
{

	/**
	 * @inheritDoc
	 */
	public function checkGame(GameInterface $game, HighlightCollection $highlights): void {
		$pairs = [];
		$maxHitsOwn = 0;
		/** @var Player[] $maxHitsOwnPlayers */
		$maxHitsOwnPlayers = [];
		$maxDeathsOwn = 0;
		/** @var Player[] $maxDeathsOwnPlayers */
		$maxDeathsOwnPlayers = [];
		/** @var Player $player */
		foreach ($game->players->getAll() as $player) {
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
								lang(
									         'Hráči %s a %s se oba navzájem zasáhli %dx.',
									context: 'hits',
									domain : 'highlights'
								),
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

		if ($game->mode?->isTeam()) {
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
									context: 'results',
									domain : 'highlights'
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
									context: 'results',
									domain : 'highlights'
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
								lang(
									         '%s byli zasaženi nejvíce spoluhráči (%d).',
									context: 'results',
									domain : 'highlights'
								),
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

	public function checkPlayer(PlayerInterface $player, HighlightCollection $highlights): void {
		assert($player instanceof Player);
		$name1 = $player->name;
		$gender1 = GenderService::rankWord($name1);

		if ($player->game->mode?->isTeam()) {
			if ($player->hitsOwn > $player->hitsOther) {
				$highlights->add(
					new GameHighlight(
						GameHighlightType::HITS,
						sprintf(
							lang(
								match ($gender1) {
									Gender::MALE   => '%s zasáhl více spoluhráčů (%d), než protihráčů (%d)',
									Gender::FEMALE => '%s zasáhla více spoluhráčů (%d), než protihráčů (%d)',
									Gender::OTHER  => '%s zasáhlo více spoluhráčů (%d), než protihráčů (%d)',
								},
								context: 'hits',
								domain : 'highlights'
							),
							'@' . $name1 . '@',
							$player->hitsOwn,
							$player->hitsOther,
						),
						GameHighlight::VERY_HIGH_RARITY + 20
					)
				);
			}

			if ($player->favouriteTarget !== null &&  $player->favouriteTarget->color === $player->color) {
				$name2 = $player->favouriteTarget->name ?? '';
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
									Gender::MALE   => '%s zasáhl ' . $name2Verb . ' %s vícekrát (%d), než kteréhokoliv protihráče',
									Gender::FEMALE => '%s zasáhla ' . $name2Verb . ' %s vícekrát (%d), než kteréhokoliv protihráče',
									Gender::OTHER  => '%s zasáhlo ' . $name2Verb . ' %s vícekrát (%d), než kteréhokoliv protihráče',
								},
								context: 'hits',
								domain : 'highlights'
							),
							'@' . $name1 . '@',
							'@' . $name2 . '@<' . NameInflectionService::accusative($name2) . '>',
							$player->getHitsPlayer($player->favouriteTarget),
						),
						GameHighlight::VERY_HIGH_RARITY + 20
					)
				);
			}
		}

		// Check if player hit some other players the same number of times
		/** @var string[][] $hitCounts */
		$hitCounts = [];
		foreach ($player->getHitsPlayers() as $hit) {
			if ($hit->count === 0) {
				continue;
			}
			$hitCounts[$hit->count] ??= [];
			$hitCounts[$hit->count][] = '@' . $hit->playerTarget->name . '@<' . NameInflectionService::accusative(
					$hit->playerTarget->name
				) . '>';
		}
		foreach ($hitCounts as $count => $hits) {
			$hitCounts = count($hits);
			if ($hitCounts < 2) {
				continue;
			}

			$lastName = array_pop($hits);
			$highlights->add(
				new GameHighlight(
					GameHighlightType::HITS,
					sprintf(
						lang(
							match ($gender1) {
								Gender::MALE   => '%s zasáhl hráče %s a %s stejněkrát (%d)',
								Gender::FEMALE => '%s zasáhla hráče %s a %s stejněkrát (%d)',
								Gender::OTHER  => '%s zasáhlo hráče %s a %s stejněkrát (%d)',
							},
							context: 'hits',
							domain : 'highlights'
						),
						'@' . $name1 . '@',
						implode(', ', $hits),
						$lastName,
						$count
					),
					GameHighlight::MEDIUM_RARITY + (10 * $hitCounts) + (2 * $count)
				)
			);
		}
	}
}