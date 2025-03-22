<?php

namespace App\Models\Group;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player as GamePlayer;
use App\GameModels\Game\Team;
use InvalidArgumentException;
use Lsr\Lg\Results\Interface\Models\GroupPlayerInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Wrapper class to aggregate values from multiple player instances
 *
 * @method string getTeamColor()
 * @method string getColor()
 * @method Team getTeam()
 * @method Game getGame()
 */
class Player implements GroupPlayerInterface
{
	use PlayerAggregate;

	/** @var array<string,Player> */
	private static array $cache = [];
	public string $name = '';
	/** @var PlayerModeAggregate[] $gameModes */
	public array $gameModes = [];
	/** @var PlayerHit[] */
	public array $hitPlayers = [];
	/** @var PlayerHit[] */
	public array $deathPlayers = [];

	public function __construct(
		public int                 $groupId,
		public string              $asciiName,
		public readonly GamePlayer $player,
	) {
	}

	public static function get(
		int $groupId,
		string $asciiName,
		GamePlayer $player
	) : Player {
		$key = $groupId . '_' . $asciiName;
		self::$cache[$key] ??= new self($groupId, $asciiName, $player);
		return self::$cache[$key];
	}

	/**
	 * @return array{
	 *     groupId: int,
	 *      asciiName: string,
	 *      player: array{id: int, system: string},
	 *      name: string,
	 *      gameModes: PlayerModeAggregate[],
	 *      hitPlayers: PlayerHit[],
	 *      deathPlayers: PlayerHit[],
	 *      gameCodes: string[],
	 *      playCount: int,
	 *      hits: int[],
	 *      deaths: int[],
	 *      hitsOwn: int[],
	 *      deathsOwn: int[],
	 *      shots: int[],
	 *      misses: int[],
	 *      skills: int[],
	 *      vests: array<string|int, int>,
	 *      accuracies: int[],
	 *      scores: int[]
	 *  }
	 */
	public function __serialize(): array {
		return [
			'groupId'   => $this->groupId,
			'asciiName' => $this->asciiName,
			'player'    => [
				'id'     => $this->player->id,
				'system' => $this->player::SYSTEM,
			],

			'name'         => $this->name,
			'gameModes'    => $this->gameModes,
			'hitPlayers'   => $this->hitPlayers,
			'deathPlayers' => $this->deathPlayers,

			'gameCodes'  => $this->gameCodes,
			'playCount'  => $this->playCount,
			'hits'       => $this->hits,
			'deaths'     => $this->deaths,
			'hitsOwn'    => $this->hitsOwn,
			'deathsOwn'  => $this->deathsOwn,
			'shots'      => $this->shots,
			'misses'     => $this->misses,
			'skills'     => $this->skills,
			'vests'      => $this->vests,
			'accuracies' => $this->accuracies,
			'scores'     => $this->scores,
		];
	}

	/**
	 * @param array{
	 *     groupId: int,
	 *     asciiName: string,
	 *     player: array{id: int, system: string},
	 *     name: string,
	 *     gameModes: PlayerModeAggregate[],
	 *     hitPlayers: PlayerHit[],
	 *     deathPlayers: PlayerHit[],
	 *     gameCodes: string[],
	 *     playCount: int,
	 *     hits: int[],
	 *     deaths: int[],
	 *     hitsOwn: int[],
	 *     deathsOwn: int[],
	 *     shots: int[],
	 *     misses: int[],
	 *     skills: int[],
	 *     vests: array<string|int, int>,
	 *     accuracies: int[],
	 *     scores: int[]
	 * } $data
	 *
	 * @throws ReflectionException
	 */
	public function __unserialize(array $data): void {
		$this->groupId = $data['groupId'];
		$this->asciiName = $data['asciiName'];

		// Lazy loaded player class
		/** @var class-string<GamePlayer> $playerClass */
		$playerClass = match ($data['player']['system']) {
			'evo6'       => \App\GameModels\Game\Evo6\Player::class,
			'evo5'       => \App\GameModels\Game\Evo5\Player::class,
			'laserforce' => \App\GameModels\Game\LaserForce\Player::class,
			default      => throw new InvalidArgumentException('Invalid system'),
		};
		$reflector = new ReflectionClass($playerClass);
		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		$this->player = $reflector->newLazyProxy(static fn() => $playerClass::get($data['player']['id']));

		$this->name = $data['name'];
		$this->gameModes = $data['gameModes'];
		$this->hitPlayers = $data['hitPlayers'];
		$this->deathPlayers = $data['deathPlayers'];

		$this->gameCodes = $data['gameCodes'];
		$this->playCount = $data['playCount'];
		$this->hits = $data['hits'];
		$this->deaths = $data['deaths'];
		$this->hitsOwn = $data['hitsOwn'];
		$this->deathsOwn = $data['deathsOwn'];
		$this->shots = $data['shots'];
		$this->misses = $data['misses'];
		$this->skills = $data['skills'];
		$this->vests = $data['vests'];
		$this->accuracies = $data['accuracies'];
		$this->scores = $data['scores'];

		self::$cache[$this->groupId . '_' . $this->asciiName] = $this;
	}

	/**
	 * @return PlayerHit[]
	 */
	public function getHitPlayersSorted(): array {
		uasort($this->hitPlayers, static fn($hit1, $hit2) => $hit2->countEnemy - $hit1->countEnemy);
		return $this->hitPlayers;
	}

	/**
	 * @return PlayerHit[]
	 */
	public function getDeathPlayersSorted(): array {
		uasort($this->deathPlayers, static fn($hit1, $hit2) => $hit2->countEnemy - $hit1->countEnemy);
		return $this->deathPlayers;
	}

	public function addGame(GamePlayer $player, ?Game $game = null): void {
		if (!isset($game)) {
			$game = $player->game;
		}

		// Prevent duplicate adding
		if (in_array($game->code, $this->gameCodes, true)) {
			return;
		}

		$this->playCount++;

		// Check name
		if ($this->name === $this->asciiName && $player->name !== $this->asciiName) {
			$this->name = $player->name; // Prefer non-ascii (with diacritics) names
		}

		// Add values
		$this->skills[] = $player->skill;
		$this->accuracies[] = $player->accuracy;
		$this->scores[] = $player->score;
		$this->hits[] = $player->hits;
		$this->deaths[] = $player->deaths;
		$this->accuracies[] = $player->accuracy;
		$this->shots[] = $player->shots;

		if (isset($player->hitsOwn) && $game->mode?->isTeam()) {
			$this->hitsOwn[] = $player->hitsOwn;
		}
		if (isset($player->deathsOwn) && $game->mode?->isTeam()) {
			$this->deathsOwn[] = $player->deathsOwn;
		}

		// Add vest
		if (!isset($this->vests[$player->vest])) {
			$this->vests[$player->vest] = 0;
		}
		$this->vests[$player->vest]++;

		// Add aggregate values for game mode
		try {
			if (isset($game->mode->id)) {
				if (!isset($this->gameModes[$game->mode->id])) {
					$this->gameModes[$game->mode->id] = new PlayerModeAggregate($game->mode);
				}
				$this->gameModes[$game->mode->id]->addGame($player, $game);
			}
		} catch (GameModeNotFoundException) {
		}

		// Log game code
		$this->gameCodes[] = $game->code;
	}

	/**
	 * @param string           $name
	 * @param array<int,mixed> $arguments
	 *
	 * @return mixed
	 */
	public function __call(string $name, array $arguments): mixed {
		return $this->player->$name(...$arguments);
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get($name): mixed {
		return $this->player->$name;
	}

	/**
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public function __set($name, $value): void {
		$this->player->$name = $value;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function __isset($name): bool {
		return isset($this->player->$name);
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return int
	 */
	public function getModesSumShots(array $modeIds): int {
		$sum = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumShots();
			}
		}
		return $sum;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return float
	 */
	public function getModesAverageShots(array $modeIds): float {
		$sum = 0;
		$count = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumShots();
				$count += count($this->gameModes[$id]->shots);
			}
		}
		if ($count === 0) {
			return 0;
		}
		return $sum / $count;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return float
	 */
	public function getModesAverageMisses(array $modeIds): float {
		$sum = 0;
		$count = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumMisses();
				$count += count($this->gameModes[$id]->getMisses());
			}
		}
		if ($count === 0) {
			return 0;
		}
		return $sum / $count;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return float
	 */
	public function getModesAverageAccuracy(array $modeIds): float {
		$sumHits = 0;
		$sumShots = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sumHits += $this->gameModes[$id]->getSumHits();
				$sumShots += $this->gameModes[$id]->getSumShots();
			}
		}
		if ($sumShots === 0) {
			return 0;
		}
		return round(100 * $sumHits / $sumShots);
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return float
	 */
	public function getModesAverageHits(array $modeIds): float {
		$sum = 0;
		$count = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumHits();
				$count += count($this->gameModes[$id]->hits);
			}
		}
		if ($count === 0) {
			return 0;
		}
		return $sum / $count;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return float
	 */
	public function getModesAverageOwnHits(array $modeIds): float {
		$sum = 0;
		$count = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumOwnHits();
				$count += count($this->gameModes[$id]->hitsOwn);
			}
		}
		if ($count === 0) {
			return 0;
		}
		return $sum / $count;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return float
	 */
	public function getModesAverageDeaths(array $modeIds): float {
		$sum = 0;
		$count = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumDeaths();
				$count += count($this->gameModes[$id]->deaths);
			}
		}
		if ($count === 0) {
			return 0;
		}
		return $sum / $count;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return float
	 */
	public function getModesAverageOwnDeaths(array $modeIds): float {
		$sum = 0;
		$count = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumOwnDeaths();
				$count += count($this->gameModes[$id]->deathsOwn);
			}
		}
		if ($count === 0) {
			return 0;
		}
		return $sum / $count;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return int
	 */
	public function getModesSumScore(array $modeIds): int {
		$sum = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumScore();
			}
		}
		return $sum;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return float
	 */
	public function getModesAverageScore(array $modeIds): float {
		$sum = 0;
		$count = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumScore();
				$count += count($this->gameModes[$id]->scores);
			}
		}
		if ($count === 0) {
			return 0;
		}
		return $sum / $count;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return int
	 */
	public function getModesSkill(array $modeIds): int {
		$sum = 0;
		$count = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += array_sum($this->gameModes[$id]->skills);
				$count += count($this->gameModes[$id]->shots);
			}
		}
		if ($count === 0) {
			return 0;
		}
		return (int)round($sum / $count);
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return int|string
	 */
	public function getModesFavouriteVest(array $modeIds): int|string {
		$vests = [];
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				foreach ($this->gameModes[$id]->vests as $vest => $count) {
					if (!isset($vests[$vest])) {
						$vests[$vest] = 0;
					}
					$vests[$vest] += $count;
				}
			}
		}

		arsort($vests);
		return array_key_first($vests) ?? 1;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return float
	 */
	public function getModesKd(array $modeIds): float {
		$sum = 0;
		$sumDeath = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumHits();
				$sumDeath += $this->gameModes[$id]->getSumDeaths();
			}
		}
		return $sum / ($sumDeath === 0 ? 1 : $sumDeath);
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return int
	 */
	public function getModesSumHits(array $modeIds): int {
		$sum = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumHits();
			}
		}
		return $sum;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return int
	 */
	public function getModesSumDeaths(array $modeIds): int {
		$sum = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumDeaths();
			}
		}
		return $sum;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return int
	 */
	public function getModesSumOwnDeaths(array $modeIds): int {
		$sum = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumOwnDeaths();
			}
		}
		return $sum;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return int
	 */
	public function getModesSumOwnHits(array $modeIds): int {
		$sum = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumOwnHits();
			}
		}
		return $sum;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return int
	 */
	public function getModesSumMisses(array $modeIds): int {
		$sum = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->getSumMisses();
			}
		}
		return $sum;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return int
	 */
	public function getModesPlayCount(array $modeIds): int {
		$sum = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->playCount;
			}
		}
		return $sum;
	}

}