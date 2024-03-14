<?php

namespace App\Services\GameHighlight;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Models\DataObjects\Highlights\HighlightCollection;

interface TeamHighlightChecker
{
	/**
	 * Check highlights for a team
	 *
	 * @template P of Player
	 * @template G of Game
	 *
	 * @param Team<P, G>          $team
	 * @param HighlightCollection $highlights
	 *
	 * @return void
	 */
	public function checkTeam(Team $team, HighlightCollection $highlights): void;
}