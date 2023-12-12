<?php

namespace App\Models\Events;

use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_event_date')]
class EventDate extends Model
{

	public const TABLE = 'event_dates';

	#[ManyToOne]
	public Event $event;

	public \DateTimeInterface  $start;
	public ?\DateTimeInterface $end = null;

	public ?string $description = null;

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