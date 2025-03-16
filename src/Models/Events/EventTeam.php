<?php

namespace App\Models\Events;

use App\Models\Tournament\League\League;
use App\Models\Tournament\League\LeagueTeam;
use Lsr\Db\DB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ValidationException;

/**
 * @extends EventTeamBase<EventPlayer>
 */
#[PrimaryKey('id_team')]
class EventTeam extends EventTeamBase
{
	use WithLeagueTeam;

	public const string TABLE        = 'event_teams';
	public const TOKEN_KEY    = 'event-team';
	public const PLAYER_CLASS = EventPlayer::class;

	#[ManyToOne]
	public Event $event;

	/** @var EventDate[] */
	#[ManyToMany('event_team_date', class: EventDate::class)]
	public array $dates = [];

	public function save(): bool {
		if (isset($this->tournament->league)) {
			$this->createUpdateLeagueTeam();
		}
		$success = parent::save();
		if ($success) {
			DB::delete('event_team_date', ['id_team = %i', $this->id]);
			$values = [];
			foreach ($this->dates as $date) {
				$values[] = ['id_team' => $this->id, 'id_event_date' => $date->id];
			}
			bdump($values);
			if (!empty($values)) {
				DB::insert('event_team_date', ...$values);
			}
		}
		return $success;
	}

	/**
	 * @return void
	 * @throws ValidationException
	 */
	protected function createUpdateLeagueTeam(): void {
		if (!isset($this->event->league)) {
			return;
		}
		if (!isset($this->leagueTeam)) {
			$this->leagueTeam = new LeagueTeam();
		}
		$this->leagueTeam->league = $this->event->league;
		$this->leagueTeam->name = $this->name;
		$this->leagueTeam->image = $this->image;
		$this->leagueTeam->save();
	}

	public function getEvent(): EventBase|League {
		return $this->event;
	}
}