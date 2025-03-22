<?php

namespace App\Models\Group;

use App\GameModels\Game\Game;
use App\GameModels\Game\Team as GameTeam;
use InvalidArgumentException;
use Lsr\Lg\Results\Interface\Models\GroupPlayerInterface;
use Lsr\Lg\Results\Interface\Models\GroupTeamInterface;
use ReflectionClass;

class Team implements GroupTeamInterface
{

	public const array BASIC_TEAM_NAMES = [
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

	/** @var array<string, Team> */
	private static array $cache = [];


	/** @var GroupPlayerInterface[] */
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
	public array $drawsTeams = [];
	public int   $wins       = 0;
	public int   $losses     = 0;
	public int   $draws      = 0;
	public float $skill {
		get {
			$this->skill ??= array_sum($this->skills) / (count($this->skills) > 0 ? count($this->skills) : 1);
			return $this->skill;
		}
	}
	public int   $scoreSum {
		get {
			$this->scoreSum ??= array_sum($this->scores);
			return $this->scoreSum;
		}
	}
	public float $scoreAvg {
		get {
			$this->scoreAvg ??= $this->scoreSum / (count($this->scores) > 0 ? count($this->scores) : 1);
			return $this->scoreAvg;
		}
	}
	public int   $hitsSum {
		get {
			$this->hitsSum ??= array_sum($this->hits);
			return $this->hitsSum;
		}
	}
	public float $hitsAvg {
		get {
			$this->hitsAvg ??= $this->hitsSum / (count($this->hits) > 0 ? count($this->hits) : 1);
			return $this->hitsAvg;
		}
	}
	public int   $hitsOwnSum {
		get {
			$this->hitsOwnSum ??= array_sum($this->hitsOwn);
			return $this->hitsOwnSum;
		}
	}
	public float $hitsOwnAvg {
		get {
			$this->hitsOwnAvg ??= $this->hitsOwnSum / (count($this->hitsOwn) > 0 ? count($this->hitsOwn) : 1);
			return $this->hitsOwnAvg;
		}
	}
	public int   $deathsSum {
		get {
			$this->deathsSum ??= array_sum($this->deaths);
			return $this->deathsSum;
		}
	}
	public float $deathsAvg {
		get {
			$this->deathsAvg ??= $this->deathsSum / (count($this->deaths) > 0 ? count($this->deaths) : 1);
			return $this->deathsAvg;
		}
	}
	public int   $deathsOwnSum {
		get {
			$this->deathsOwnSum ??= array_sum($this->deathsOwn);
			return $this->deathsOwnSum;
		}
	}
	public float $deathsOwnAvg {
		get {
			$this->deathsOwnAvg ??= $this->deathsOwnSum / (count($this->deathsOwn) > 0 ? count(
					$this->deathsOwn
				) : 1);
			return $this->deathsOwnAvg;
		}
	}
	public int   $shotsSum {
		get {
			$this->shotsSum ??= array_sum($this->shots);
			return $this->shotsSum;
		}
	}
	public float $shotsAvg {
		get {
			$this->shotsAvg ??= $this->shotsSum / (count($this->shots) > 0 ? count($this->shots) : 1);
			return $this->shotsAvg;
		}
	}
	public int   $missSum {
		get {
			$this->missSum ??= array_sum($this->misses);
			return $this->missSum;
		}
	}
	public float $missAvg {
		get {
			$this->missAvg ??= $this->missSum / (count($this->misses) > 0 ? count($this->misses) : 1);
			return $this->missAvg;
		}
	}
	public float $kd {
		get {
			$this->kd ??= $this->hitsSum / ($this->deathsSum > 0 ? $this->deathsSum : 1);
			return $this->kd;
		}
	}
	public float $accuracyAvg {
		get {
			$this->accuracyAvg ??= array_sum($this->accuracies) / (count($this->accuracies) > 0 ? count(
					$this->accuracies
				) : 1);
			return $this->accuracyAvg;
		}
	}
	public int   $points {
		get => ($this->wins * 3) + $this->draws;
	}

	/**
	 * @var array<int|string>
	 */
	public array $colors = [];

	public function __construct(
		public int               $groupId,
		public string            $key,
		public readonly GameTeam $team,
	) {
	}

	public function __serialize(): array {
		return [
			'groupId'     => $this->groupId,
			'key'         => $this->key,
			'team'        => [
				'id'     => $this->team->id,
				'system' => $this->team::SYSTEM,
			],
			'players'     => array_map(
				static fn(Player $player) => [
					'group' => $player->groupId,
					'name' => $player->asciiName,
					'player' => [
						'id' => $player->player->id,
						'system' => $player->player::SYSTEM,
					]
				],
				$this->players
			),
			'teams'       => array_map(
				static fn(GameTeam $team) => [
					'id' => $team->id,
					'system' => $team::SYSTEM
				],
				$this->teams
			),
			'playCount'   => $this->playCount,
			'name'        => $this->name,
			'names'       => $this->names,
			'gameCodes'   => $this->gameCodes,
			'scores'      => $this->scores,
			'positions'   => $this->positions,
			'hits'        => $this->hits,
			'deaths'      => $this->deaths,
			'hitsOwn'     => $this->hitsOwn,
			'deathsOwn'   => $this->deathsOwn,
			'shots'       => $this->shots,
			'misses'      => $this->misses,
			'accuracies'  => $this->accuracies,
			'skills'      => $this->skills,
			'gamesTeams'  => $this->gamesTeams,
			'hitTeams'    => $this->hitTeams,
			'deathTeams'  => $this->deathTeams,
			'winsTeams'   => $this->winsTeams,
			'lossesTeams' => $this->lossesTeams,
			'drawsTeams'  => $this->drawsTeams,
			'wins'        => $this->wins,
			'losses'      => $this->losses,
			'draws'       => $this->draws,
			'colors'      => $this->colors,
		];
	}

	/**
	 * @param array{
	 *     groupId: int,
	 *     key: string,
	 *     team: array{id: int, system: string},
	 *     players: array{group:int, name:string, player:array{id:int,system:string}}[],
	 *     teams: array{id: int, system: string}[],
	 *     playCount: int,
	 *     name: string,
	 *     names: string[],
	 *     gameCodes: string[],
	 *     scores: int[],
	 *     positions: int[],
	 *     hits: int[],
	 *     deaths: int[],
	 *     hitsOwn: int[],
	 *     deathsOwn: int[],
	 *     shots: int[],
	 *     misses: int[],
	 *     accuracies: float[],
	 *     skills: float[],
	 *     gamesTeams: int[],
	 *     hitTeams: int[],
	 *     deathTeams: int[],
	 *     winsTeams: int[],
	 *     lossesTeams: int[],
	 *     drawsTeams: int[],
	 *     wins: int,
	 *     losses: int,
	 *     draws: int,
	 *     colors: int[]
	 * } $data
	 *
	 * @throws \ReflectionException
	 */
	public function __unserialize(array $data): void {
		$this->groupId = $data['groupId'];
		$this->key = $data['key'];

		// Lazy loaded team class
		/** @var class-string<GameTeam> $teamClass */
		$teamClass = match ($data['team']['system']) {
			'evo6'       => \App\GameModels\Game\Evo6\Team::class,
			'evo5'       => \App\GameModels\Game\Evo5\Team::class,
			'laserforce' => \App\GameModels\Game\LaserForce\Team::class,
			default      => throw new InvalidArgumentException('Invalid system'),
		};
		$reflector = new ReflectionClass($teamClass);
		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->team = $reflector->newLazyProxy(static fn() => $teamClass::get($data['team']['id']));
		$this->players = [];
		$playerReflector = new ReflectionClass(Player::class);
		foreach ($data['players'] as $player) {
			/** @var class-string<\App\GameModels\Game\Player> $playerClass */
			$playerClass = match ($player['player']['system']) {
				'evo6'       => \App\GameModels\Game\Evo6\Player::class,
				'evo5'       => \App\GameModels\Game\Evo5\Player::class,
				'laserforce' => \App\GameModels\Game\LaserForce\Player::class,
				default      => throw new InvalidArgumentException('Invalid system'),
			};
			$reflector = new ReflectionClass($playerClass);
			$this->players[$player['name']] = $playerReflector->newLazyProxy(
				static fn() => Player::get(
					$player['group'],
					$player['name'],
					$reflector->newLazyProxy(static fn() => $playerClass::get($player['player']['id']))
				)
			);
		}
		$this->teams = [];
		foreach ($data['teams'] as $team) {
			/** @var class-string<GameTeam> $teamClass */
			$teamClass = match ($team['system']) {
				'evo6'       => \App\GameModels\Game\Evo6\Team::class,
				'evo5'       => \App\GameModels\Game\Evo5\Team::class,
				'laserforce' => \App\GameModels\Game\LaserForce\Team::class,
				default      => throw new InvalidArgumentException('Invalid system'),
			};
			$reflector = new ReflectionClass($teamClass);
			$this->teams[] = $reflector->newLazyProxy(static fn() => $teamClass::get($team['id']));
		}
		$this->playCount = $data['playCount'];
		$this->name = $data['name'];
		$this->names = $data['names'];
		$this->gameCodes = $data['gameCodes'];
		$this->scores = $data['scores'];
		$this->positions = $data['positions'];
		$this->hits = $data['hits'];
		$this->deaths = $data['deaths'];
		$this->hitsOwn = $data['hitsOwn'];
		$this->deathsOwn = $data['deathsOwn'];
		$this->shots = $data['shots'];
		$this->misses = $data['misses'];
		$this->accuracies = $data['accuracies'];
		$this->skills = $data['skills'];
		$this->gamesTeams = $data['gamesTeams'];
		$this->hitTeams = $data['hitTeams'];
		$this->deathTeams = $data['deathTeams'];
		$this->winsTeams = $data['winsTeams'];
		$this->lossesTeams = $data['lossesTeams'];
		$this->drawsTeams = $data['drawsTeams'];
		$this->wins = $data['wins'];
		$this->losses = $data['losses'];
		$this->draws = $data['draws'];
		$this->colors = $data['colors'];

		self::$cache[$this->groupId . '_' . $this->key] = $this;
	}

	public static function get(
		int      $groupId,
		string   $key,
		GameTeam $team
	): Team {
		$cacheKey = $groupId . '_' . $key;
		self::$cache[$cacheKey] ??= new self($groupId, $key, $team);
		return self::$cache[$cacheKey];
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


	public function addColor(int|string $color): void {
		$this->colors[$color] = $color;
	}

	public function addPlayer(GroupPlayerInterface ...$players): void {
		foreach ($players as $player) {
			$this->players[$player->asciiName] = $player;
		}
	}
}