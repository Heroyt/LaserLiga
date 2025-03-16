<?php

namespace App\Models\Tournament\League;

use App\Models\Arena;
use App\Models\BaseModel;
use App\Models\Events\Event;
use App\Models\Events\EventPopup;
use App\Models\Events\EventRegistrationInterface;
use App\Models\Events\EventRegistrationTrait;
use App\Models\Events\EventTeamBase;
use App\Models\Tournament\EventPriceGroup;
use App\Models\Tournament\RegistrationType;
use App\Models\Tournament\Tournament;
use App\Models\WithSchema;
use Lsr\Core\App;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_league'), OA\Schema]
class League extends BaseModel implements EventRegistrationInterface, WithSchema
{
	use EventRegistrationTrait;

	public const string TABLE = 'leagues';

	#[OA\Property]
	public string     $name;
	#[OA\Property]
	public ?string    $slug             = null;
	#[OA\Property]
	public ?string    $shortDescription = null;
	#[OA\Property]
	public ?string    $description      = null;
	#[OA\Property]
	public ?string    $price            = null;
	#[OA\Property]
	public ?string    $image            = null;
	#[OA\Property, Instantiate]
	public EventPopup $popup;

	#[OA\Property]
	public RegistrationType $registrationType = RegistrationType::TOURNAMENT;

	#[OA\Property, ManyToOne]
	public ?EventPriceGroup $eventPriceGroup = null;

	#[ManyToOne]
	#[OA\Property]
	public Arena $arena;

	/** @var Tournament[] */
	private array $tournaments = [];

	/** @var LeagueCategory[] */
	private array $categories = [];
	/** @var LeagueTeam[] */
	private array $teams;

	/** @var Event[] */
	private array $events;

	public static function getBySlug(string $slug): ?League {
		return self::query()->where('slug = %s', $slug)->first();
	}

	/**
	 * @return LeagueCategory[]
	 * @throws ValidationException
	 */
	public function getCategories(): array {
		if (empty($this->categories)) {
			$this->categories = LeagueCategory::query()->where('id_league = %i', $this->id)->get();
		}
		return $this->categories;
	}

	/**
	 * @return void
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 */
	public function countPoints(): void {
		/** @var array<int,int> $pointsAll */
		$pointsAll = [];
		foreach ($this->getTournaments() as $tournament) {
			if (!$tournament->isFinished()) {
				continue;
			}
			$teams = $tournament->sortedTeams;
			$teamCount = count($teams);
			foreach (array_reverse($teams) as $key => $team) {
				if (!isset($team->leagueTeam)) {
					continue;
				}
				$pointsAll[(int)$team->leagueTeam->id] ??= 0; // Initialize empty values for teams
				$pointsAll[(int)$team->leagueTeam->id] += $key + match ($teamCount - $key) {
						1       => 7, // Will get 4 points more than the second place (4 + 2 extra from second + 1 extra from third)
						2       => 4, // Will get 3 points more than the third place (3 + 1 extra from third)
						3       => 2, // Will get 2 points more than the fourth place
						default => 1, // Will get 1 point more than the last place
					};
			}
		}

		foreach ($pointsAll as $id => $points) {
			$team = LeagueTeam::get($id);
			$team->points = $points;
			$team->save();
		}
	}

	/**
	 * @return Tournament[]
	 */
	public function getTournaments(): array {
		if (empty($this->tournaments)) {
			$this->tournaments = Tournament::query()->where('id_league = %i AND active = 1', $this->id)->get();
		}
		return $this->tournaments;
	}

	/**
	 * @return LeagueTeam[]
	 * @throws ValidationException
	 */
	public function getTeams(bool $excludeDisqualified = false): array {
		$this->teams ??= LeagueTeam::query()->where('id_league = %i', $this->id)->get();
		if ($excludeDisqualified) {
			return array_filter($this->teams, static fn(EventTeamBase $team) => !$team->disqualified);
		}
		return $this->teams;
	}

	public function getSchema(): array {
		$schema = [
			'@context'            => 'https://schema.org/',
			'@type'               => 'EventSeries',
			'name'                => $this->name,
			'eventAttendanceMode' => 'OfflineEventAttendanceMode',
			'identifier'          => $this->getUrl(),
			'url'                 => $this->getUrl(),
			'keywords'            => 'Laser Game, tournament, turnaj, Laser liga, turnaj laser game',
			'organizer'           => [
				'@type'      => 'Organization',
				'identifier' => $this->arena->getUrl(),
				'name'       => $this->arena->name,
				'url'        => $this->arena->getUrl(),
				'logo'       => $this->arena->getLogoUrl(),
			],
			'offers'              => [],
			'subEvent'            => [],
			'eventStatus'         => 'EventScheduled',
		];

		if ($this->image !== null) {
			$schema['image'] = $this->getImageUrl();
		}

		if ($this->shortDescription !== null) {
			$schema['description'] = $this->shortDescription;
		}

		if ($this->teamLimit !== null) {
			$schema['maximumAttendeeCapacity'] = $this->teamLimit;
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

		foreach ($this->getTournaments() as $tournament) {
			$schema['subEvent'][] = $tournament->getSchema();
		}
		foreach ($this->getEvents() as $event) {
			$schema['subEvent'][] = $event->getSchema();
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
		return array_values(
			array_merge(
				!empty($this->slug) ? ['liga', (string)$this->slug] : ['league', (string)$this->id],
				array_map(static fn($val) => (string)$val, $append)
			)
		);
	}

	public function getImageUrl(): ?string {
		if (!isset($this->image)) {
			return null;
		}
		return App::getInstance()->getBaseUrl() . $this->image;
	}

	public function getRegistrationUrl(): string {
		return $this->getUrl('registration');
	}

	/**
	 * @return Event[]
	 * @throws ValidationException
	 */
	public function getEvents(): array {
		$this->events ??= Event::query()->where('id_league = %i', $this->id)->get();
		return $this->events;
	}
}