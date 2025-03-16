<?php

namespace App\Services\GameHighlight\Checkers;

use App\GameModels\Game\Player;
use App\GameModels\Game\PlayerTrophy;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\HighlightCollection;
use App\Models\DataObjects\Highlights\TrophyHighlight;
use App\Services\GameHighlight\PlayerHighlightChecker;
use Lsr\Lg\Results\Interface\Models\PlayerInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;

class TrophyHighlightChecker implements PlayerHighlightChecker
{

	public function checkPlayer(PlayerInterface $player, HighlightCollection $highlights): void {
		assert($player instanceof Player);
		foreach (PlayerTrophy::SPECIAL_TROPHIES as $trophy) {
			try {
				if ($player->getTrophy()->check($trophy)) {
					$highlights->add(new TrophyHighlight($trophy, $player, GameHighlight::VERY_HIGH_RARITY));
				}
			} catch (ModelNotFoundException|ValidationException|DirectoryCreationException) {
			}
		}
		foreach (PlayerTrophy::RARE_TROPHIES as $trophy) {
			try {
				if ($player->getTrophy()->check($trophy)) {
					$highlights->add(
						new TrophyHighlight(
							$trophy,
							$player,
							GameHighlight::HIGH_RARITY - ($trophy === 'favouriteTargetOf' ? 10 : 0)
						)
					);
				}
			} catch (ModelNotFoundException|ValidationException|DirectoryCreationException) {
			}
		}
	}

}