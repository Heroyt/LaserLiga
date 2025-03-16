<?php

namespace App\Services\GameHighlight;

use App\Models\DataObjects\Highlights\HighlightCollection;
use Lsr\Lg\Results\Interface\Models\GameInterface;

interface GameHighlightChecker
{

	/**
	 * Check highlights for game
	 */
	public function checkGame(GameInterface $game, HighlightCollection $highlights): void;

}