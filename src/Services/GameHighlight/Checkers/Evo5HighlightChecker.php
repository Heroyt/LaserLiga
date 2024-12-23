<?php

namespace App\Services\GameHighlight\Checkers;

use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\Game;
use App\Helpers\Gender;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\GameHighlightType;
use App\Models\DataObjects\Highlights\HighlightCollection;
use App\Services\GameHighlight\GameHighlightChecker;
use App\Services\GenderService;

class Evo5HighlightChecker implements GameHighlightChecker
{

	public const int DUPLICATE_MAX_POWER = 99999;

	/**
	 * @inheritDoc
	 */
	public function checkGame(Game $game, HighlightCollection $highlights): void {
		if (!$game instanceof \App\GameModels\Game\Evo5\Game) {
			return;
		}

		$mineDeaths = [];
		$powers = [];
		$powersMaxPlayer = null;
		$powersMax = 0;
		$powersSecondMax = 0;
		/** @var Player $player */
		foreach ($game->getPlayers()->getAll() as $player) {
			if ($player->minesHits > 0) {
				$mineDeaths[] = $player;
			}
			if (($bonusCount = $player->bonus->getSum()) > 0) {
				$powers[] = $powers;
				if ($bonusCount > $powersMax) {
					$powersSecondMax = $powersMax;
					$powersMax = $bonusCount;
					$powersMaxPlayer = $player;
				}
				else if ($bonusCount > $powersSecondMax && $bonusCount < $powersMax) {
					$powersSecondMax = $bonusCount;
				}
				else if ($bonusCount === $powersMax) {
					$powersMax = self::DUPLICATE_MAX_POWER;
					$powersMaxPlayer = null;
				}
			}
		}

		if (isset($mineDeaths[0]->name) && count($mineDeaths) === 1) {
			$name = $mineDeaths[0]->name;
			$gender = GenderService::rankWord($name);
			$highlights->add(
				new GameHighlight(
					GameHighlightType::ALONE_STATS, sprintf(
					lang(
						match ($gender) {
							Gender::MALE   => '%s jediný byl zasažen minou.',
							Gender::FEMALE => '%s jediná byla zasažena minou.',
							Gender::OTHER  => '%s jediné bylo zasaženo minou.',
						},
						context: 'pod',
						domain: 'highlights',
					),
					'@' . $name . '@'
				),  GameHighlight::VERY_HIGH_RARITY
				)
			);
		}

		if (isset($powers[0]->name) && count($powers) === 1) {
			$name = $powers[0]->name;
			$gender = GenderService::rankWord($name);
			$highlights->add(
				new GameHighlight(
					GameHighlightType::ALONE_STATS, sprintf(
					lang(
						match ($gender) {
							Gender::MALE   => '%s jediný získal bonus.',
							Gender::FEMALE => '%s jediná získala bonusy.',
							Gender::OTHER  => '%s jediné získalo bonusy.',
						},
						context: 'pod',
						domain: 'highlights',
					),
					'@' . $name . '@'
				),  GameHighlight::VERY_HIGH_RARITY
				)
			);
		}

		if (isset($powersMaxPlayer) && $powersSecondMax !== self::DUPLICATE_MAX_POWER && $powersSecondMax !== 0 && ($ratio = $powersMax / $powersSecondMax) >= 2) {
			$ratio = floor($ratio * 2) / 2;
			$name = $powersMaxPlayer->name;
			$gender = GenderService::rankWord($name);
			$highlights->add(
				new GameHighlight(
					GameHighlightType::ALONE_STATS, sprintf(
					lang(
						match ($gender) {
							Gender::MALE   => '%s získal %.1fx tolik bonusů co ostatní.',
							Gender::FEMALE => '%s získala %.1fx tolik bonusů co ostatní.',
							Gender::OTHER  => '%s získalo %.1fx tolik bonusů co ostatní.',
						},
						context: 'pod',
						domain: 'highlights',
					),
					'@' . $name . '@',
					$ratio
				),  GameHighlight::HIGH_RARITY
				)
			);
		}
	}
}