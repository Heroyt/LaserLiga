<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Player;

class PlayerUserRank
{
	public int    $id_user;
	public int    $position;
	public string $position_text;
}