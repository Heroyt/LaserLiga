<?php
declare(strict_types=1);

namespace App\Templates\Games;

use App\GameModels\Game\Game;
use App\GameModels\Game\Team;
use Lsr\Core\Controllers\TemplateParameters;

class GameTeamParameters extends TemplateParameters
{
	public Game $game;
	public Team $team;
	public int  $maxShots = 1000;
}