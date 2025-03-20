<?php
declare(strict_types=1);

namespace App\Templates\Games;

use App\GameModels\Game\Game;
use App\GameModels\Game\Today;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\Photos\Photo;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class GameParameters extends TemplateParameters
{
	use PageTemplateParameters;

	public ?User $user = null;
	public Game $game;
	public string $gameDescription = '';
	/** @var array<string,mixed> */
	public array $schema = [];
	public string $prevGame = '';
	public string $nextGame = '';
	public string $prevUserGame = '';
	public string $nextUserGame = '';
	public ?LigaPlayer $activeUser = null;
	public Today $today;
	/** @var Photo[] */
	public array $photos = [];
	public bool $canDownloadPhotos = false;

}