<?php
declare(strict_types=1);

namespace App\Templates\Games;

use App\Models\DataObjects\Game\LeaderboardPlayer;
use Lsr\Core\Controllers\TemplateParameters;

class GameTodayLeaderboardParameters extends TemplateParameters
{

	public int $highlight = 0;
	public string $property = 'Score';
	/** @var LeaderboardPlayer[] */
	public array $players = [];

}