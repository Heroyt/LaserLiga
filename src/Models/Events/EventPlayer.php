<?php

namespace App\Models\Events;

use App\Models\Tournament\League\League;
use App\Models\Tournament\League\Player as LeaguePlayer;
use Lsr\Core\DB;
use Lsr\Core\Models\Attributes\ManyToMany;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;

#[PrimaryKey('id_player')]
class EventPlayer extends EventPlayerBase
{
	public const TABLE     = 'event_players';
	public const TOKEN_KEY = 'event-player';

	#[ManyToOne]
	public Event $event;

	#[ManyToOne('id_player', 'id_league_player')]
	public ?LeaguePlayer $leaguePlayer = null;

	#[ManyToOne]
	public ?EventTeam $team = null;

	/** @var EventDate[] */
	#[ManyToMany('event_player_date', class: EventDate::class)]
	public array $dates = [];

	public function save(): bool {
		$success = parent::save();
		if ($success) {
			DB::delete('event_player_date', ['id_player = %i', $this->id]);
			$values = [];
			foreach ($this->dates as $date) {
				$values[] = ['id_player' => $this->id, 'id_event_date' => $date->id];
			}
			bdump($values);
			if (!empty($values)) {
				DB::insert('event_player_date', ...$values);
			}
		}
		return $success;
	}

	public function getEvent(): EventBase|League {
		return $this->event;
	}
}