<?php
declare(strict_types=1);

namespace App\Templates\Kiosk;

use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\DataObjects\MusicGroup;
use App\Models\DataObjects\Player\LeaderboardRank;
use App\Templates\AutoFillParameters;
use App\Templates\GameFilters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class DashboardParameters extends TemplateParameters
{
	use PageTemplateParameters;
	use AutoFillParameters;
	use GameFilters;

	public ?User $user = null;
	public DashboardType $type = DashboardType::DASHBOARD;
	public Arena $arena;
	public int $todayGames = 0;
	public int $todayPlayers = 0;
	/** @var array<string,MusicGroup> */
	public array $music = [];

	public string $activeType = 'rank';

	public ?LigaPlayer $searchedPlayer = null;
	public int $userOrder = -1;

	/** @var LigaPlayer[] */
	public array $players = [];
	/** @var LeaderboardRank[] */
	public array $ranks = [];
}