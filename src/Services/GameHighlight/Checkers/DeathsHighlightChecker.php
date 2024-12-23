<?php

namespace App\Services\GameHighlight\Checkers;

use App\GameModels\Game\Lasermaxx\Game as LaserMaxxGame;
use App\GameModels\Game\Player;
use App\Helpers\Gender;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\GameHighlightType;
use App\Models\DataObjects\Highlights\HighlightCollection;
use App\Services\GameHighlight\PlayerHighlightChecker;
use App\Services\GenderService;
use App\Services\NameInflectionService;

class DeathsHighlightChecker implements PlayerHighlightChecker
{

	/**
	 * @inheritDoc
	 */
	public function checkPlayer(Player $player, HighlightCollection $highlights): void {
		$name = $player->name;
		$gender = GenderService::rankWord($name);
		if ($player->getGame()->getMode()?->isTeam() && $player->deathsOwn > $player->deathsOther) {
			$highlights->add(
				new GameHighlight(
					GameHighlightType::DEATHS,
					sprintf(
						lang(
							match ($gender) {
								Gender::MALE   => '%s byl zasažen více spoluhráči (%d), než protihráči (%d)',
								Gender::FEMALE => '%s byla zasažena více spoluhráči (%d), než protihráči (%d)',
								Gender::OTHER  => '%s bylo zasaženo více spoluhráči (%d), než protihráči (%d)',
							},
							context: 'deaths',
							domain : 'highlights'
						),
						'@' . $name . '@<' . NameInflectionService::genitive($name) . '>',
						$player->deathsOwn,
						$player->deathsOther
					),
					GameHighlight::VERY_HIGH_RARITY + 20
				)
			);
		}

		if (($game = $player->getGame()) instanceof LaserMaxxGame) {
			$secondsTotal = $player->deaths * $game->respawn;
			$minutes = $secondsTotal / 60;
			$seconds = $secondsTotal % 60;
			$gameLength = $game->getRealGameLength();

			if ($minutes / $gameLength > 0.3) {
				$highlights->add(
					new GameHighlight(
						GameHighlightType::DEATHS,
						sprintf(
							lang(
								match ($gender) {
									Gender::MALE   => '%s strávil %s ve hře vypnutý.',
									Gender::FEMALE => '%s strávila %s ve hře vypnutá.',
									Gender::OTHER  => '%s strávilo %s ve hře vypnuté.',
								} . ($minutes / $gameLength > 0.5 ? ' To je víc než polovina hry!' : ''),
								context: 'deaths',
								domain: 'highlights'
							),
							'@' . $name . '@',
							sprintf(
								lang('%d minutu', '%d minut', (int)floor($minutes), 'trvání'),
								floor($minutes)
							) . ($seconds > 0 ? ' ' . lang('a', context: 'spojka') . ' ' . sprintf(
									lang('%d sekundu', '%d sekund', $seconds, 'trvání'),
									$seconds
								) : '')
						),
						(int)(GameHighlight::MEDIUM_RARITY + round(50 * $minutes / $gameLength))
					)
				);
			}
		}
	}
}