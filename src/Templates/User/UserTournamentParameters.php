<?php
declare(strict_types=1);

namespace App\Templates\User;

use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\Tournament\Player;
use App\Models\Tournament\Tournament;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class UserTournamentParameters extends TemplateParameters
{
	use PageTemplateParameters;

	public ?User $user = null;
	public LigaPlayer $currPlayer;
	/** @var Tournament[] */
	public array $tournaments;
	/** @var Player[] */
	public array $players;
}