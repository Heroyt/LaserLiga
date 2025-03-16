<?php

namespace App\Models\Tournament;

use App\Models\Events\EventBase;
use App\Models\Events\EventRegistrationInterface;
use App\Models\Events\EventTeamBase;
use App\Models\GameGroup;
use App\Models\Tournament\League\League;
use App\Models\Tournament\League\LeagueCategory;
use App\Models\WithSchema;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Core\App;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\Exceptions\ValidationException;
use Lsr\Orm\ModelCollection;
use Lsr\Orm\ModelQuery;
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
class Tournament extends EventBase implements EventRegistrationInterface, WithSchema
{

	public const string TABLE = 'tournaments';

	#[OA\Property]
	public int $teamsInGame = 2;

	#[ManyToOne]
	public ?League         $league   = null;
	#[ManyToOne]
	public ?LeagueCategory $category = null;

	#[ManyToOne]
	public ?GameGroup $group = null;

	/** @var ModelCollection<Group> */
	#[OneToMany(class: Group::class)]
	public ModelCollection $groups;

	#[Instantiate]
	#[OA\Property]
	public TournamentPoints $points;
	#[OA\Property]
	public DateTimeInterface  $start;
	#[OA\Property]
	public ?DateTimeInterface $end = null;
	/** @var Team[] */
	protected array $teams = [];
	/** @var Player[] */
	protected array $players = [];
	protected bool $started;
	/** @var Game[] */
	private array $games = [];
	/** @var Progression[] */
	public array $progressions = [] {
		get {
			if (empty($this->progressions)) {
				$this->progressions = Progression::query()->where('id_tournament = %i', $this->id)->get();
			}
			return $this->progressions;
		}
	}
	/** @var Team[] */
	public array $sortedTeams = [] {
		get {
			if (empty($this->sortedTeams)) {
				$teams = $this->teams;
				usort($teams, static function (Team $a, Team $b) {
					$diff = $b->points - $a->points;
					if ($diff !== 0) {
						return $diff;
					}
					return $b->score - $a->score;
				});
				$this->sortedTeams = $teams;
			}
			return $this->sortedTeams;
		}
	}

	public function isFinished(): bool {
		if (!$this->finished) {
			if (count($this->games) === 0) {
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
		return isset($this->teamLimit) && count($this->teams) >= $this->teamLimit;
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

	public function isRegistrationActive(): bool {
		return $this->registrationsActive && !$this->isStarted();
	}

	public function isStarted(): bool {
		$this->started ??= $this->start < (new DateTimeImmutable());
		return $this->started;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getSchema(): array {
		$schema = [
			'@context'            => 'https://schema.org/',
			'@type'               => 'SportsEvent',
			'name'                => $this->name,
			'startDate'           => $this->start->format('c'),
			'eventAttendanceMode' => 'OfflineEventAttendanceMode',
			'sport'               => 'Laser Game',
			'identifier'          => $this->getUrl(),
			'url'                 => $this->getUrl(),
			'keywords'            => 'Laser Game, tournament, turnaj, Laser liga, turnaj laser game',
			'competitor'          => [],
			'organizer'           => [
				'@type'      => 'Organization',
				'identifier' => $this->arena->getUrl(),
				'name'       => $this->arena->name,
				'url'        => $this->arena->getUrl(),
				'logo'       => $this->arena->getLogoUrl(),
			],
			'offers'              => [],
			'eventStatus'         => 'EventScheduled',
		];

		if ($this->image !== null) {
			$schema['image'] = $this->getImageUrl();
		}

		if ($this->end !== null) {
			$schema['endDate'] = $this->end->format('c');
		}

		if ($this->shortDescription !== null) {
			$schema['description'] = $this->shortDescription;
		}

		if ($this->teamLimit !== null) {
			$schema['maximumAttendeeCapacity'] = $this->teamLimit;
		}

		if ($this->league !== null) {
			$schema['alternateName'] = $this->league->name . ': ' . $this->name;
			$schema['superEvent'] = [
				'@type'               => 'EventSeries',
				'identifier'          => $this->league->getUrl(),
				'url'                 => $this->league->getUrl(),
				'name'                => $this->league->name,
				'keywords'            => 'Laser Game, tournament, turnaj, Laser liga, turnaj laser game',
				'eventAttendanceMode' => 'OfflineEventAttendanceMode',
			];
		}

		if ($this->arena->address->isFilled()) {
			$schema['location'] = [
				'@type'   => 'Place',
				'name'    => $this->arena->name,
				'url'     => $this->arena->web,
				'logo'    => $this->arena->getLogoUrl(),
				'address' => [
					'@type'           => 'PostalAddress',
					'streetAddress'   => $this->arena->address->street,
					'addressLocality' => $this->arena->address->city,
					'postalCode'      => $this->arena->address->postCode,
					'addressCountry'  => $this->arena->address->country,
				],
			];
			if ($this->arena->lng !== null) {
				$schema['location']['longitude'] = $this->arena->lng;
			}
			if ($this->arena->lat !== null) {
				$schema['location']['latitude'] = $this->arena->lat;
			}
			$schema['organizer']['address'] = $schema['location']['address'];
		}

		if ($this->eventPriceGroup !== null && count($this->eventPriceGroup->prices) > 0) {
			foreach ($this->eventPriceGroup->prices as $price) {
				$schema['offers'][] = [
					'@type'         => 'Offer',
					'price'         => $price->price,
					'priceCurrency' => 'CZK',
					'name'          => $price->description,
					'description'   => $this->eventPriceGroup->description,
					'url'           => $this->getRegistrationUrl(),
				];
			}
		}

		foreach ($this->getTeams(true) as $team) {
			$teamData = [
				'@type'   => 'SportsTeam',
				'name'    => $team->name,
				'sport'   => 'Laser Game',
				'athlete' => [],
			];

			if ($team->image !== null) {
				$teamData['logo'] = $team->getImageUrl();
			}

			if ($team->leagueTeam !== null) {
				$teamData['url'] = App::getLink(['league', 'team', (string) $team->leagueTeam->id]);
			}

			foreach ($team->players as $player) {
				$playerData = [
					'@type' => 'Person',
					'name'  => $player->nickname,
				];
				if ($player->user !== null) {
					$playerData['identifier'] = $player->user->getCode();
					$playerData['url'] = App::getLink(['user', $player->user->getCode()]);
				}
				$teamData['athlete'][] = $playerData;
			}

			$schema['competitor'][] = $teamData;
		}

		return $schema;
	}

	public function getUrl(string|int ...$append): string {
		return App::getLink($this->getUrlPath(...$append));
	}

	/**
	 * @param string|int ...$append
	 *
	 * @return list<string>
	 */
	public function getUrlPath(string|int ...$append): array {
		/** @phpstan-ignore return.type */
		return array_values(
			array_merge(
				['tournament', $this->id],
				array_map(static fn($val) => (string)$val, $append)
			)
		);
	}

	public function getRegistrationUrl(): string {
		if ($this->league !== null && $this->league->registrationType === RegistrationType::LEAGUE) {
			return $this->league->getUrl('register');
		}
		return $this->getUrl('register');
	}

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

}