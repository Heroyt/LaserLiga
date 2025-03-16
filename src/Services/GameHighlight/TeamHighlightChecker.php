<?php

namespace App\Services\GameHighlight;

use App\Models\DataObjects\Highlights\HighlightCollection;
use Lsr\Lg\Results\Interface\Models\TeamInterface;

interface TeamHighlightChecker
{
	/**
	 * Check highlights for a team
	 */
	public function checkTeam(TeamInterface $team, HighlightCollection $highlights): void;
}