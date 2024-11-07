<?php
declare(strict_types=1);

namespace App\Templates\Player;

use App\Models\Auth\User;
use App\Models\DataObjects\Game\PlayerGamesGame;
use App\Models\DataObjects\Player\PlayerRank;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class ProfileParameters extends TemplateParameters
{
	use PageTemplateParameters;

	public User $user;
	public ?User $loggedInUser = null;
	/** @var PlayerGamesGame[] */
	public array $lastGames = [];
	public PlayerRank $rankOrder;
}