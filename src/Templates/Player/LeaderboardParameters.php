<?php
declare(strict_types=1);

namespace App\Templates\Player;

use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\DataObjects\Player\LeaderboardRank;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class LeaderboardParameters extends TemplateParameters
{
	use PageTemplateParameters;

	public ?Arena $arena = null;
	public ?User $user = null;
	public ?LigaPlayer $searchedPlayer = null;
	public int $userOrder = -1;

	/** @var LigaPlayer[] */
	public array $players = [];
	/** @var LeaderboardRank[] */
	public array $ranks = [];

	public string $activeType = 'rank';
	public int $p = 1;
	public int $pages = 1;
	public int $limit = 15;
	public int $total = 0;
	public string $orderBy = 'rank';
	public bool $desc = true;
}