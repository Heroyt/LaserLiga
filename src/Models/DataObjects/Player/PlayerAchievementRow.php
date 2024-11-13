<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Player;

use DateTimeInterface;

class PlayerAchievementRow
{
	public int $id_user;
	public int $id_achievement;
	public string $code;
	public DateTimeInterface $datetime;
}