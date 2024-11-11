<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Game;

class DuplicateGameCheckRow
{
	public string $code;
	public int $id_game;
	public int $id_mode;
	public int $id_music;
	public int $id_group;
}