<?php

namespace App\Services\GameHighlight;

use App\GameModels\Game\Game;
use App\GameModels\Game\PlayerTrophy;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\HighlightCollection;
use App\Models\DataObjects\Highlights\TrophyHighlight;
use App\Services\GameHighlight\GameHighlightChecker;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Exceptions\DirectoryCreationException;

class TrophyHighlightChecker implements GameHighlightChecker
{

	/**
	 * @inheritDoc
	 */
	public function checkGame(Game $game, HighlightCollection $highlights): void {
		foreach ($game->getPlayers()->getAll() as $player) {
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
}