<?php

namespace App\Models\Events;

use App\Models\Tournament\League\LeagueTeam;
use Lsr\Orm\Attributes\Relations\ManyToOne;

trait WithLeagueTeam
{

	#[ManyToOne('id_team', 'id_league_team')]
	public ?LeagueTeam $leagueTeam = null;

}