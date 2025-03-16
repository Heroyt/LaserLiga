<?php

namespace App\Services\GameHighlight;

use App\Models\DataObjects\Highlights\HighlightCollection;
use Lsr\Lg\Results\Interface\Models\PlayerInterface;

interface PlayerHighlightChecker
{

	/**
	 * Check highlights for a player
	 */
	public function checkPlayer(PlayerInterface $player, HighlightCollection $highlights): void;

}