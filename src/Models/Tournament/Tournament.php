<?php

namespace App\Models\Tournament;

use App\GameModels\Game\Enums\GameModeType;
use App\Models\Arena;
use DateTimeInterface;
use Lsr\Core\App;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_tournament')]
class Tournament extends Model
{

	public const TABLE = 'tournaments';

	#[ManyToOne]
	public Arena   $arena;
	#[ManyToOne]
	public ?League $league = null;

	/** @var Group[] */
	#[OneToMany(class: Group::class)]
	public array $groups = [];

	public string $name;
	public ?string $description = null;

	public ?int $teamLimit = null;

	public ?string $image = null;
	public ?string $prices = null;
	public ?string $resultsSummary = null;
	public GameModeType $format = GameModeType::TEAM;
	public int $teamSize = 1;
	public int $subCount = 0;

	public bool $active = true;
	public bool $registrationsActive = true;

	public DateTimeInterface  $start;
	public ?DateTimeInterface $end = null;

	#[Instantiate]
	public Requirements $requirements;

	/** @var Team[] */
	private array $teams = [];
	/** @var Player[] */
	private array $players = [];

	public function getImageUrl() : ?string {
		if (!isset($this->image)) {
			return null;
		}
		return App::getUrl().$this->image;
	}

	/**
	 * @return Team[]
	 * @throws ValidationException
	 */
	public function getTeams() : array {
		if ($this->format === GameModeType::SOLO) {
			return [];
		}
		if (empty($this->teams)) {
			$this->teams = Team::query()->where('id_tournament = %i', $this->id)->get();
		}
		return $this->teams;
	}

	/**
	 * @return Player[]
	 * @throws ValidationException
	 */
	public function getPlayers() : array {
		if ($this->format === GameModeType::TEAM) {
			return [];
		}
		if (empty($this->players)) {
			$this->players = Player::query()->where('id_tournament = %i', $this->id)->get();
		}
		return $this->players;
	}

}