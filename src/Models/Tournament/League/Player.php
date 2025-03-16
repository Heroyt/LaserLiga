<?php

namespace App\Models\Tournament\League;

use App\Models\Events\EventBase;
use App\Models\Events\EventPlayerBase;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_player')]
class Player extends EventPlayerBase
{
	public const string TABLE     = 'league_players';
	public const TOKEN_KEY = 'league-player';

	#[ManyToOne]
	public League      $league;
	#[ManyToOne]
	public ?LeagueTeam $team = null;

	public function getEvent(): EventBase|League {
		return $this->league;
	}
}