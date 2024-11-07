<?php
declare(strict_types=1);

namespace App\Templates\Games;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use Lsr\Core\Controllers\TemplateParameters;

class GamePlayerEloParameters extends TemplateParameters
{
	public Game $game;
	public Player $player;
}