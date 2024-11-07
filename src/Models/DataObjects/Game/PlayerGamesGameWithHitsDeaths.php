<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Game;

class PlayerGamesGameWithHitsDeaths extends PlayerGamesGame
{
	public int $hits;
	public int $deaths;
}