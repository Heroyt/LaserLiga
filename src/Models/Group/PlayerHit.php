<?php

namespace App\Models\Group;

class PlayerHit
{

	public function __construct(
		public Player $playerShot,
		public Player $playerTarget,
		public int    $countEnemy = 0,
		public int    $countTeammate = 0,
		public int    $gamesEnemy = 0,
		public int    $gamesTeammate = 0,
	) {
	}

}