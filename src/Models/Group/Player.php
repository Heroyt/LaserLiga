<?php

namespace App\Models\Group;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player as GamePlayer;
use App\GameModels\Game\Team;

/**
 * Wrapper class to aggregate values from multiple player instances
 *
 * @method string getTeamColor()
 * @method string getColor()
 * @method Team getTeam()
 * @method Game getGame()
 */
class Player
{
	use PlayerAggregate;

	public string $name = '';

	/** @var PlayerModeAggregate[] $gameModes */
	public array $gameModes = [];

	public function __construct(
		public readonly string     $asciiName,
		public readonly GamePlayer $player,
	) {
	}

	public function addGame(GamePlayer $player, ?Game $game = null) : void {
		if (!isset($game)) {
			$game = $player->getGame();
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

		// Add vest
		if (!isset($this->vests[$player->vest])) {
			$this->vests[$player->vest] = 0;
		}
		$this->vests[$player->vest]++;

		// Add aggregate values for game mode
		if (isset($game->mode->id)) {
			if (!isset($this->gameModes[$game->mode->id])) {
				$this->gameModes[$game->mode->id] = new PlayerModeAggregate($game->mode);
			}
			$this->gameModes[$game->mode->id]->addGame($player, $game);
		}

		// Log game code
		$this->gameCodes[] = $game->code;
	}

	/**
	 * @param string $name
	 * @param array  $arguments
	 *
	 * @return mixed
	 */
	public function __call($name, $arguments) : mixed {
		return $this->player->$name(...$arguments);
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get($name) : mixed {
		return $this->player->$name;
	}

	/**
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public function __set($name, $value) : void {
		$this->player->$name = $value;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function __isset($name) : bool {
		return isset($this->player->$name);
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return int
	 */
	public function getModesSumShots(array $modeIds) : int {
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
	public function getModesAverageShots(array $modeIds) : float {
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
	public function getModesAverageAccuracy(array $modeIds) : float {
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
	public function getModesAverageHits(array $modeIds) : float {
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
	public function getModesAverageDeaths(array $modeIds) : float {
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
	 * @return int
	 */
	public function getModesSumScore(array $modeIds) : int {
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
	public function getModesAverageScore(array $modeIds) : float {
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
	public function getModesSkill(array $modeIds) : int {
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
		return (int) round($sum / $count);
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return int|string
	 */
	public function getModesFavouriteVest(array $modeIds) : int|string {
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
	public function getModesKd(array $modeIds) : float {
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
	public function getModesSumHits(array $modeIds) : int {
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
	public function getModesSumDeaths(array $modeIds) : int {
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
	public function getModesPlayCount(array $modeIds) : int {
		$sum = 0;
		foreach ($modeIds as $id) {
			if (isset($this->gameModes[$id])) {
				$sum += $this->gameModes[$id]->playCount;
			}
		}
		return $sum;
	}

}