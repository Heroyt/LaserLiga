<?php
declare(strict_types=1);

namespace App\Templates\Arena;

use App\Models\Arena;
use App\Models\DataObjects\Game\LeaderboardRecord;
use App\Models\DataObjects\MusicGroup;
use App\Models\MusicMode;
use App\Templates\GameFilters;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class ArenaParameters extends TemplateParameters
{
	use PageTemplateParameters;
	use AutoFillParameters;
	use GameFilters;

	public string $tab = 'stats';
	public Arena $arena;
	public ?\DateTimeInterface $date = null;
	public int $todayGames;
	public int $todayPlayers;
	/** @var MusicGroup[] */
	public array $music = [];
	/** @var iterable<LeaderboardRecord>  */
	public iterable $players;

}