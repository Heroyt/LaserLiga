<?php

namespace App\Models\Events;

use App\Models\Tournament\League\League;
use App\Models\Tournament\RegistrationType;
use App\Models\WithSchema;
use Lsr\Core\App;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ValidationException;

#[PrimaryKey('id_event')]
class Event extends EventBase implements EventRegistrationInterface, WithSchema
{

	public const string TABLE = 'events';
	#[ManyToOne]
	public ?League   $league    = null;
	public DatesType $datesType = DatesType::MULTIPLE;
	/** @var EventTeam[] */
	protected array $teams = [];
	/** @var EventPlayer[] */
	protected array $players = [];
	/** @var EventDate[] */
	private array $dates;

	public function getSchema(): array {
		$schema = [
			'@context'            => 'https://schema.org/',
			'@type'               => 'SportsEvent',
			'name'                => $this->name,
			'startDate'           => [],
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

		foreach ($this->getDates() as $date) {
			$schema['startDate'][] = $date->start->format('c');
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

		foreach ($this->teams as $team) {
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
				['events', $this->id],
				array_map(static fn($val) => (string)$val, $append)
			)
		);
	}

	/**
	 * @return EventDate[]
	 * @throws ValidationException
	 */
	public function getDates(): array {
		$this->dates ??= EventDate::query()->where('id_event = %i', $this->id)->orderBy('start')->get();
		return $this->dates;
	}

	public function getRegistrationUrl(): string {
		if ($this->league !== null && $this->league->registrationType === RegistrationType::LEAGUE) {
			return $this->league->getUrl('register');
		}
		return $this->getUrl('register');
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
}