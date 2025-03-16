<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Player;

use DateTimeInterface;

class PlayerGameSkillRow
{
	public string            $code;
	public string            $system;
	public int               $id_game;
	public int               $skill;
	public DateTimeInterface $start;
}