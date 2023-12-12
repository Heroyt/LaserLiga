<?php

namespace App\Models\DataObjects\Event;

use App\Models\Auth\LigaPlayer;
use App\Models\Tournament\League\Player;
use App\Models\Tournament\PlayerSkill;

class PlayerRegistrationDTO
{

	public ?int    $playerId     = null;
	public ?Player $leaguePlayer = null;

	/** @var array<int, int[]> */
	public array $events = [];

	public function __construct(
		public string      $nickname,
		public string      $name,
		public string      $surname,
		public ?string     $email = null,
		public ?string     $phone = null,
		public ?string     $parentEmail = null,
		public ?string     $parentPhone = null,
		public ?int        $birthYear = null,
		public PlayerSkill $skill = PlayerSkill::BEGINNER,
		public ?LigaPlayer $user = null,
		public bool        $captain = false,
		public bool        $sub = false,
	) {
	}

}