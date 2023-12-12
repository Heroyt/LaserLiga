<?php

namespace App\Models\Group;

use App\GameModels\Game\Game;
use App\GameModels\Game\Team as GameTeam;

class Team
{

	public const BASIC_TEAM_NAMES = [
		'cerveny tym',
		'zeleny tym',
		'modry tym',
		'zluty tym',
		'oceanovy tym',
		'ruzovy tym',
		'cerveny',
		'zeleny',
		'modry',
		'zluty',
		'oceanovy',
		'ruzovy',
		'red team',
		'green team',
		'blue team',
		'yellow team',
		'pink team',
		'ocean team',
		'red',
		'green',
		'blue',
		'yellow',
		'pink',
		'ocean',
	];


	/** @var Player[] */
	public array $players = [];
	/** @var GameTeam[] */
	public array $teams = [];

	public int $playCount = 0;

	public string $name = '';
	/** @var string[] */
	public array $names = [];
	/** @var string[] */
	public array $gameCodes = [];
	/** @var int[] */
	public array $scores = [];
	/** @var int[] */
	public array $positions = [];

	/** @var int[] */
	public array $hits = [];
	/** @var int[] */
	public array $deaths = [];
	/** @var int[] */
	public array $hitsOwn = [];
	/** @var int[] */
	public array $deathsOwn = [];
	/** @var int[] */
	public array $shots = [];
	/** @var int[] */
	public array $misses = [];
	/** @var float[] */
	public array $accuracies = [];
	/** @var float[] */
	public array $skills = [];

	/** @var array<string,int> */
	public array $gamesTeams = [];
	/** @var array<string,int> */
	public array $hitTeams = [];
	/** @var array<string,int> */
	public array $deathTeams = [];
	/** @var array<string,int> */
	public array $winsTeams = [];
	/** @var array<string,int> */
	public array $lossesTeams = [];
	/** @var array<string,int> */
	public array  $drawsTeams = [];
	public int    $wins       = 0;
	public int    $losses     = 0;
	public int    $draws      = 0;
	private float $skillAvg;
	private int   $scoreSum;
	private float $scoreAvg;
	private int   $hitsSum;
	private float $hitsAvg;
	private int   $hitsOwnSum;
	private float $hitsOwnAvg;
	private int   $deathsSum;
	private float $deathsAvg;
	private int   $deathsOwnSum;
	private float $deathsOwnAvg;
	private int   $shotsSum;
	private float $shotsAvg;
	private int   $missSum;
	private float $missAvg;
	private float $kd;
	private float $accuracyAvg;

	public function __construct(
		public readonly string   $key,
		public readonly GameTeam $team,
	) {
	}

	/**
	 * Adds a game to the stats.
	 *
	 * @param Game     $game The game object to add.
	 * @param GameTeam $team The team object for the game.
	 *
	 * @return void
	 */
	public function addGame(Game $game, GameTeam $team): void {
		$this->playCount++;
		$this->names[] = $team->name;
		$this->teams[] = $team;

		$this->gameCodes[] = $game->code;

		// Stats
		$this->scores[$game->code] = $team->score;
		$this->positions[$game->code] = $team->position;
		$this->hits[$game->code] = $team->getHits();
		$this->deaths[$game->code] = $team->getDeaths();
		$this->hitsOwn[$game->code] = $team->getHitsOwn();
		$this->deathsOwn[$game->code] = $team->getDeathsOwn();
		$this->shots[$game->code] = $team->getShots();
		$this->misses[$game->code] = $this->shots[$game->code] - $this->hits[$game->code];
		$this->accuracies[$game->code] = $team->getAccuracy();
		$this->skills[$game->code] = $team->getSkill();
	}

	public function getSkill(): float {
		$this->skillAvg ??= array_sum($this->skills) / (count($this->skills) > 0 ? count($this->skills) : 1);
		return $this->skillAvg;
	}

	public function getPoints(): int {
		return ($this->wins * 3) + $this->draws;
	}

	public function getHitsAvg(): float {
		$this->hitsAvg ??= $this->getHitsSum() / (count($this->hits) > 0 ? count($this->hits) : 1);
		return $this->hitsAvg;
	}

	public function getHitsSum(): int {
		$this->hitsSum ??= array_sum($this->hits);
		return $this->hitsSum;
	}

	public function getDeathsAvg(): float {
		$this->deathsAvg ??= $this->getDeathsSum() / (count($this->deaths) > 0 ? count($this->deaths) : 1);
		return $this->deathsAvg;
	}

	public function getDeathsSum(): int {
		$this->deathsSum ??= array_sum($this->deaths);
		return $this->deathsSum;
	}

	public function getScoreAvg(): float {
		$this->scoreAvg ??= $this->getScoreSum() / (count($this->scores) > 0 ? count($this->scores) : 1);
		return $this->scoreAvg;
	}

	public function getScoreSum(): int {
		$this->scoreSum ??= array_sum($this->scores);
		return $this->scoreSum;
	}

	public function getHitsOwnAvg(): float {
		$this->hitsOwnAvg ??= $this->getHitsOwnSum() / (count($this->hitsOwn) > 0 ? count($this->hitsOwn) : 1);
		return $this->hitsOwnAvg;
	}

	public function getHitsOwnSum(): int {
		$this->hitsOwnSum ??= array_sum($this->hitsOwn);
		return $this->hitsOwnSum;
	}

	public function getDeathsOwnAvg(): float {
		$this->deathsOwnAvg ??= $this->getDeathsOwnSum() / (count($this->deathsOwn) > 0 ? count($this->deathsOwn) : 1);
		return $this->deathsOwnAvg;
	}

	public function getDeathsOwnSum(): int {
		$this->deathsOwnSum ??= array_sum($this->deathsOwn);
		return $this->deathsOwnSum;
	}

	public function getShotsAvg(): float {
		$this->shotsAvg ??= $this->getShotsSum() / (count($this->shots) > 0 ? count($this->shots) : 1);
		return $this->shotsAvg;
	}

	public function getShotsSum(): int {
		$this->shotsSum ??= array_sum($this->shots);
		return $this->shotsSum;
	}

	public function getMissAvg(): float {
		$this->missAvg ??= $this->getMissSum() / (count($this->misses) > 0 ? count($this->misses) : 1);
		return $this->missAvg;
	}

	public function getMissSum(): int {
		$this->missSum ??= array_sum($this->misses);
		return $this->missSum;
	}

	public function getAccuracyAvg(): float {
		$this->accuracyAvg ??= array_sum($this->accuracies) / (count($this->accuracies) > 0 ? count(
				$this->accuracies
			) : 1);
		return $this->accuracyAvg;
	}

	public function getKd(): float {
		$this->kd ??= $this->getHitsSum() / ($this->getDeathsSum() > 0 ? $this->getDeathsSum() : 1);
		return $this->kd;
	}


}