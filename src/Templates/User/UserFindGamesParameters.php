<?php
declare(strict_types=1);

namespace App\Templates\User;

use App\GameModels\Game\Game;
use App\Models\Auth\User;
use App\Models\PossibleMatch;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class UserFindGamesParameters extends TemplateParameters
{
	use PageTemplateParameters;

	public User $loggedInUser;

	/** @var PossibleMatch[] */
	public array $possibleMatches = [];
	/** @var Game[] */
	public array $games = [];
}