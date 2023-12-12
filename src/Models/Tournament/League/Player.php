<?php

namespace App\Models\Tournament\League;

use App\Models\Events\EventPlayerBase;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;

#[PrimaryKey('id_player')]
class Player extends EventPlayerBase
{
	public const TABLE     = 'league_players';
	public const TOKEN_KEY = 'league-player';

	#[ManyToOne]
	public League      $league;
	#[ManyToOne]
	public ?LeagueTeam $team = null;
}