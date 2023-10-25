<?php

namespace App\Services\GameHighlight;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\HighlightCollection;

interface GameHighlightChecker
{

	/**
	 * Check highlights for game
	 *
	 * @template T of Team
	 * @template P of Player
	 * @param Game<T,P>           $game
	 * @param HighlightCollection $highlights
	 *
	 * @return void
	 */
	public function checkGame(Game $game, HighlightCollection $highlights): void;

}