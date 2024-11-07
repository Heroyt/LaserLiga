<?php
declare(strict_types=1);

namespace App\Templates\Games;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Today;
use App\Models\Achievements\PlayerAchievement;
use App\Models\Auth\User;
use Lsr\Core\Controllers\TemplateParameters;

class GamePlayerParameters extends TemplateParameters
{
	public ?User $user = null;
	public Game $game;
	public Player $player;
	public int $maxShots = 1000;
	public Today $today;
	/** @var PlayerAchievement[] */
	public array $achievements = [];
}