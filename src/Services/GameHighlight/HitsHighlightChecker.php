<?php

namespace App\Services\GameHighlight;

use App\GameModels\Game\Game;
use App\Helpers\Gender;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\GameHighlightType;
use App\Models\DataObjects\Highlights\HighlightCollection;
use App\Services\GameHighlight\GameHighlightChecker;
use App\Services\GenderService;
use App\Services\NameInflectionService;

class HitsHighlightChecker implements GameHighlightChecker
{

	/**
	 * @inheritDoc
	 */
	public function checkGame(Game $game, HighlightCollection $highlights): void {
		$pairs = [];
		foreach ($game->getPlayers()->getAll() as $player) {
			$name1 = $player->name;
			$gender1 = GenderService::rankWord($name1);
			if ($game->mode->isTeam()) {
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
									}
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
									}
								),
								'@' . $name1 . '@',
								'@' . $name2 . '@<' . NameInflectionService::accusative($name2) . '>'
							),
							GameHighlight::VERY_HIGH_RARITY + 20
						)
					);
				}
			}
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
								lang('Hráči %s a %s se oba navzájem zasáhli %dx.'),
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
	}
}