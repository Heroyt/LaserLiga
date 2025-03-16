<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Game;

use DateTimeInterface;

class LeaderboardPlayer
{

	public int               $idPlayer;
	public int               $idGame;
	public DateTimeInterface $date;
	public string            $mode;
	public string            $name;
	public int               $value;
	public int               $better;
	public int               $count;
	public int               $same;

}