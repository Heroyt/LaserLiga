<?php

namespace App\Services\GameHighlight;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Models\DataObjects\Highlights\HighlightCollection;

interface PlayerHighlightChecker
{

	/**
	 * Check highlights for a player
	 *
	 * @template T of Team
	 * @template G of Game
	 *
	 * @param Player<G, T>        $player
	 * @param HighlightCollection $highlights
	 *
	 * @return void
	 */
	public function checkPlayer(Player $player, HighlightCollection $highlights): void;

}