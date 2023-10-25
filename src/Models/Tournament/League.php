<?php

namespace App\Models\Tournament;

use App\Models\Arena;
use Lsr\Core\App;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Lsr\Logging\Exceptions\DirectoryCreationException;

#[PrimaryKey('id_league')]
class League extends Model
{

	public const TABLE = 'leagues';

	public string  $name;
	public ?string $shortDescription = null;
	public ?string $description      = null;
	public ?string $image            = null;

	#[ManyToOne]
	public Arena $arena;

	/** @var Tournament[] */
	private array $tournaments = [];

	/** @var LeagueCategory[] */
	private array $categories = [];

	public function getImageUrl(): ?string {
		if (!isset($this->image)) {
			return null;
		}
		return App::getUrl() . $this->image;
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

}