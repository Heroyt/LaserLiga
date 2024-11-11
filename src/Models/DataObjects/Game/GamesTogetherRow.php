<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Game;

use App\GameModels\Game\Enums\GameModeType;

class GamesTogetherRow
{
	public int $id_game;
	public GameModeType $type;
	public string $code;
	public string $vests;
	public string $teams;
	public string $users;
	public string $names;
}