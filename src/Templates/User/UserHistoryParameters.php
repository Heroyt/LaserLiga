<?php
declare(strict_types=1);

namespace App\Templates\User;

use App\GameModels\Game\Game;
use App\Models\Arena;
use App\Models\Auth\User;
use App\Templates\GameFilters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

/**
 * @phpstan-type FilterField array{name: string, mandatory: bool, sortable: bool}
 */
class UserHistoryParameters extends TemplateParameters
{
	use PageTemplateParameters;
	use GameFilters;

	public User $user;
	public bool $currentUser = false;
	/** @var array<string, FilterField>  */
	public array $allFields = [];
	/** @var array<string, FilterField>  */
	public array $fields = [];
	/** @var Arena[] */
	public array $arenas = [];
	/** @var Game[] */
	public array $games = [];
}