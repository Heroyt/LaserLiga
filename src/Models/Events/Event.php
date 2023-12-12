<?php

namespace App\Models\Events;

use App\GameModels\Game\Enums\GameModeType;
use App\Models\Tournament\League\League;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;

#[PrimaryKey('id_event')]
class Event extends EventBase implements EventRegistrationInterface
{

	public const TABLE = 'events';
	#[ManyToOne]
	public ?League   $league    = null;
	public DatesType $datesType = DatesType::MULTIPLE;
	/** @var EventTeam[] */
	protected array $teams = [];
	/** @var EventPlayer[] */
	protected array $players = [];
	/** @var EventDate[] */
	private array $dates;

	/**
	 * @return EventDate[]
	 * @throws ValidationException
	 */
	public function getDates(): array {
		$this->dates ??= EventDate::query()->where('id_event = %i', $this->id)->orderBy('start')->get();
		return $this->dates;
	}

	/**
	 * @return EventPlayer[]
	 * @throws ValidationException
	 */
	public function getPlayers(): array {
		if ($this->format === GameModeType::TEAM) {
			return [];
		}
		if (empty($this->players)) {
			$this->players = EventPlayer::query()->where('id_event = %i', $this->id)->get();
		}
		return $this->players;
	}

	/**
	 * @return EventTeam[]
	 * @throws ValidationException
	 */
	public function getTeams(): array {
		if ($this->format === GameModeType::SOLO) {
			return [];
		}
		if (empty($this->teams)) {
			$this->teams = EventTeam::query()->where('id_event = %i', $this->id)->get();
		}
		return $this->teams;
	}

}