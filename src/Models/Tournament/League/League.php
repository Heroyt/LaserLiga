<?php

namespace App\Models\Tournament\League;

use App\Models\Arena;
use App\Models\Events\Event;
use App\Models\Events\EventPopup;
use App\Models\Events\EventRegistrationInterface;
use App\Models\Events\EventRegistrationTrait;
use App\Models\Events\EventTeamBase;
use App\Models\Tournament\RegistrationType;
use App\Models\Tournament\Tournament;
use Lsr\Core\App;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_league'), OA\Schema]
class League extends Model implements EventRegistrationInterface
{
	use EventRegistrationTrait;

	public const TABLE = 'leagues';

	#[OA\Property]
	public string  $name;
	#[OA\Property]
	public ?string $slug  = null;
	#[OA\Property]
	public ?string $shortDescription = null;
	#[OA\Property]
	public ?string $description      = null;
	#[OA\Property]
	public ?string $price = null;
	#[OA\Property]
	public ?string $image            = null;
	#[OA\Property, Instantiate]
	public EventPopup $popup;

	#[OA\Property]
	public RegistrationType $registrationType = RegistrationType::TOURNAMENT;

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

	public function getImageUrl(): ?string {
		if (!isset($this->image)) {
			return null;
		}
		return App::getInstance()->getBaseUrl() . $this->image;
	}

	public function getUrl(string|int ...$append): string {
		return App::getLink($this->getUrlPath(...$append));
	}

	/**
	 * @param string|int ...$append
	 *
	 * @return array<string>
	 */
	public function getUrlPath(string|int ...$append): array {
		return array_merge(!empty($this->slug) ? ['liga', $this->slug] : ['league', (string) $this->id], $append);
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
			$teams = $tournament->getSortedTeams();
			$teamCount = count($teams);
			foreach (array_reverse($teams) as $key => $team) {
				if (!isset($team->leagueTeam)) {
					continue;
				}
				$pointsAll[$team->leagueTeam->id] ??= 0; // Initialize empty values for teams
				$pointsAll[$team->leagueTeam->id] += $key + match ($teamCount - $key) {
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

	/**
	 * @return Event[]
	 * @throws ValidationException
	 */
	public function getEvents(): array {
		$this->events ??= Event::query()->where('id_league = %i', $this->id)->get();
		return $this->events;
	}

}