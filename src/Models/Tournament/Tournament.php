<?php

namespace App\Models\Tournament;

use App\GameModels\Game\Enums\GameModeType;
use App\Models\Events\EventBase;
use App\Models\Events\EventRegistrationInterface;
use App\Models\Events\EventTeamBase;
use App\Models\GameGroup;
use App\Models\Tournament\League\League;
use App\Models\Tournament\League\LeagueCategory;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\ModelQuery;
use OpenApi\Attributes as OA;

#[
	PrimaryKey('id_tournament'),
	OA\Schema(
		properties: [
			new OA\Property(
				            'league',
				properties: [
					            new OA\Property('id', type: 'int'),
					            new OA\Property('name', type: 'string'),
				            ],
				type      : 'object',
				nullable  : true
			),
		]
	)
]
class Tournament extends EventBase implements EventRegistrationInterface
{

	public const TABLE = 'tournaments';

	#[OA\Property]
	public int $teamsInGame = 2;

	#[ManyToOne]
	public ?League $league = null;
	#[ManyToOne]
	public ?LeagueCategory $category = null;

	#[ManyToOne]
	public ?GameGroup $group = null;

	/** @var Group[] */
	#[OneToMany(class: Group::class)]
	public array $groups = [];

	#[Instantiate]
	#[OA\Property]
	public TournamentPoints $points;
	/** @var Team[] */
	protected array $teams = [];
	/** @var Player[] */
	protected array $players = [];
	/** @var Game[] */
	private array $games = [];
	/** @var Progression[] */
	private array $progressions = [];
	/** @var Team[] */
	private array $sortedTeams = [];


	#[OA\Property]
	public DateTimeInterface  $start;
	#[OA\Property]
	public ?DateTimeInterface $end = null;

	protected bool $started;

	/**
	 * @return Player[]
	 * @throws ValidationException
	 */
	public function getPlayers(): array {
		if ($this->format === GameModeType::TEAM) {
			return [];
		}
		if (empty($this->players)) {
			$this->players = Player::query()->where('id_tournament = %i', $this->id)->get();
		}
		return $this->players;
	}

	public function isFinished(): bool {
		if (!$this->finished) {
			if (count($this->getGames()) === 0) {
				$this->finished = false;
				return false;
			}
			$notPlayedGames = Game::query()->where('id_tournament = %i AND [code] IS NULL', $this->id)->count();
			$this->finished = $notPlayedGames === 0;
			if ($this->finished) {
				try {
					$this->save();
				} catch (ValidationException) {
				}
			}
		}
		return $this->finished;
	}

	/**
	 * @return Game[]
	 * @throws ValidationException
	 */
	public function getGames(): array {
		if (empty($this->games)) {
			$this->games = $this->queryGames()->get();
		}
		return $this->games;
	}

	/**
	 * @return ModelQuery<Game>
	 */
	public function queryGames(): ModelQuery {
		return Game::query()->where('id_tournament = %i', $this->id);
	}

	public function isFull(): bool {
		return isset($this->teamLimit) && count($this->getTeams()) >= $this->teamLimit;
	}

	/**
	 * @return Team[]
	 * @throws ValidationException
	 */
	public function getTeams(bool $excludeDisqualified = false): array {
		if ($this->format === GameModeType::SOLO) {
			return [];
		}
		if (empty($this->teams)) {
			$this->teams = Team::query()->where('id_tournament = %i', $this->id)->get();
		}
		if ($excludeDisqualified) {
			return array_filter($this->teams, static fn(EventTeamBase $team) => !$team->disqualified);
		}
		return $this->teams;
	}

	/**
	 * @return Team[]
	 * @throws ValidationException
	 */
	public function getSortedTeams(): array {
		if (empty($this->sortedTeams)) {
			$teams = $this->getTeams();
			usort($teams, static function (Team $a, Team $b) {
				$diff = $b->points - $a->points;
				if ($diff !== 0) {
					return $diff;
				}
				return $b->getScore() - $a->getScore();
			});
			$this->sortedTeams = $teams;
		}
		return $this->sortedTeams;
	}

	/**
	 * @return Progression[]
	 * @throws ValidationException
	 */
	public function getProgressions(): array {
		if (empty($this->progressions)) {
			$this->progressions = Progression::query()->where('id_tournament = %i', $this->id)->get();
		}
		return $this->progressions;
	}

	/**
	 * @return GameGroup
	 * @throws ValidationException
	 */
	public function getGroup(): GameGroup {
		if (!isset($this->group)) {
			$this->group = new GameGroup();
			$this->group->name = $this->name;
			//$this->group->active = false;
			$this->group->save();
		}
		return $this->group;
	}

	public function jsonSerialize(): array {
		$data = parent::jsonSerialize();
		if (isset($data['league'])) {
			$data['league'] = [
				'id'   => $this->league?->id,
				'name' => $this->league?->name,
			];
		}
		return $data;
	}

	public function isStarted(): bool {
		$this->started ??= $this->start < (new DateTimeImmutable());
		return $this->started;
	}

	public function isRegistrationActive(): bool {
		return $this->registrationsActive && !$this->isStarted();
	}

}