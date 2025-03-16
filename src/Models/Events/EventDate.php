<?php

namespace App\Models\Events;

use App\Models\BaseModel;
use DateTimeInterface;
use Lsr\Db\DB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ValidationException;

#[PrimaryKey('id_event_date')]
class EventDate extends BaseModel
{

	public const string TABLE = 'event_dates';

	#[ManyToOne]
	public Event $event;

	public DateTimeInterface  $start;
	public ?DateTimeInterface $end = null;

	public ?string $description = null;

	public bool $canceled = false;

	/** @var EventPlayer[] */
	private array $players;
	/** @var EventTeam[] */
	private array $teams;

	/**
	 * @return EventPlayer[]
	 * @throws ValidationException
	 */
	public function getPlayers(): array {
		$this->players ??= EventPlayer::query()
		                              ->where(
			                              'id_player IN %sql',
			                              DB::select('event_player_date', 'id_player')
			                                ->where('id_event_date = %i', $this->id)
				                              ->fluent
		                              )
		                              ->get();
		return $this->players;
	}

	/**
	 * @return EventTeam[]
	 * @throws ValidationException
	 */
	public function getTeams(): array {
		$this->teams ??= EventTeam::query()
		                          ->where(
			                          'id_team IN %sql',
			                          DB::select('event_team_date', 'id_team')
			                            ->where('id_event_date = %i', $this->id)
				                          ->fluent
		                          )
		                          ->get();
		return $this->teams;
	}

}