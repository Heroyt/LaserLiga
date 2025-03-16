<?php

namespace App\Models\Group;

trait PlayerAggregate
{
	/** @var string[] $gameCodes */
	/** @phpstan-ignore-next-line  */
	public array $gameCodes = [];

	/** @phpstan-ignore-next-line  */
	public int $playCount = 0;

	/** @var int[] $hits */
	public array $hits = [];
	/** @var int[] $deaths */
	public array $deaths = [];
	/** @var int[] $hitsOwn */
	public array $hitsOwn = [];
	/** @var int[] $deathsOwn */
	public array $deathsOwn = [];
	/** @var int[] $shots */
	public array $shots = [];
	/** @var int[] */
	public array $misses = [];
	/** @var int[] $skills */
	public array $skills = [];
	/** @var array<string|int, int> $vests */
	public array $vests = [];
	/** @var int[] $accuracies */
	public array $accuracies = [];
	/** @var int[] $scores */
	public array $scores = [];

	protected int $skillAvg;

	protected int   $scoreSum;
	protected float $scoreAvg;

	protected int   $hitsSum;
	protected int   $hitsTeamSum;
	protected float $hitsAvg;
	protected float $hitsTeamAvg;

	protected int   $deathsSum;
	protected int   $deathsTeamSum;
	protected float $deathsAvg;
	protected float $deathsTeamAvg;

	protected int   $shotsSum;
	protected int   $missSum;
	protected float $shotsAvg;
	protected float $missAvg;

	protected float $kdAvg;

	protected int $accuracyAvg;

	protected int|string $favouriteVest;

	public function getSumShots(): int {
		if (isset($this->shotsSum)) {
			return $this->shotsSum;
		}
		$this->shotsSum = array_sum($this->shots);
		return $this->shotsSum;
	}

	public function getAverageShots(): float {
		if (isset($this->shotsAvg)) {
			return $this->shotsAvg;
		}
		if (count($this->shots) === 0) {
			return 0;
		}
		$this->shotsAvg = array_sum($this->shots) / count($this->shots);
		return $this->shotsAvg;
	}

	public function getAverageAccuracy(): float {
		if (isset($this->accuracyAvg)) {
			return $this->accuracyAvg;
		}
		if (count($this->accuracies) === 0) {
			return 0;
		}
		$this->accuracyAvg = (int)round(array_sum($this->accuracies) / count($this->accuracies));
		return $this->accuracyAvg;
	}

	public function getAverageHits(): float {
		if (isset($this->hitsAvg)) {
			return $this->hitsAvg;
		}
		if (count($this->hits) === 0) {
			return 0;
		}
		$this->hitsAvg = array_sum($this->hits) / count($this->hits);
		return $this->hitsAvg;
	}

	public function getAverageDeaths(): float {
		if (isset($this->deathsAvg)) {
			return $this->deathsAvg;
		}
		if (count($this->deaths) === 0) {
			return 0;
		}
		$this->deathsAvg = array_sum($this->deaths) / count($this->deaths);
		return $this->deathsAvg;
	}

	public function getSumScore(): int {
		if (isset($this->scoreSum)) {
			return $this->scoreSum;
		}
		$this->scoreSum = array_sum($this->scores);
		return $this->scoreSum;
	}

	public function getAverageScore(): float {
		if (isset($this->scoreAvg)) {
			return $this->scoreAvg;
		}
		if (count($this->scores) === 0) {
			return 0;
		}
		$this->scoreAvg = array_sum($this->scores) / count($this->scores);
		return $this->scoreAvg;
	}

	/**
	 * @return int
	 */
	public function getSkill(): int {
		if (isset($this->skillAvg)) {
			return $this->skillAvg;
		}
		if (count($this->skills) === 0) {
			return 0;
		}
		$this->skillAvg = (int)round(array_sum($this->skills) / count($this->skills));
		return $this->skillAvg;
	}

	public function getFavouriteVest(): int {
		if (isset($this->favouriteVest)) {
			return (int) $this->favouriteVest;
		}
		arsort($this->vests);
		$this->favouriteVest = array_key_first($this->vests) ?? 1;
		return (int) $this->favouriteVest;
	}

	public function getKd(): float {
		if (isset($this->kdAvg)) {
			return $this->kdAvg;
		}
		$this->kdAvg = $this->getSumHits() / ($this->getSumDeaths() === 0 ? 1 : $this->getSumDeaths());
		return $this->kdAvg;
	}

	public function getSumHits(): int {
		if (isset($this->hitsSum)) {
			return $this->hitsSum;
		}
		$this->hitsSum = array_sum($this->hits);
		return $this->hitsSum;
	}

	public function getSumDeaths(): int {
		if (isset($this->deathsSum)) {
			return $this->deathsSum;
		}
		$this->deathsSum = array_sum($this->deaths);
		return $this->deathsSum;
	}

	public function getAverageOwnHits(): float {
		if (isset($this->hitsTeamAvg)) {
			return $this->hitsTeamAvg;
		}
		$count = count($this->hitsOwn);
		if ($count === 0) {
			return 0;
		}
		$this->hitsTeamAvg = $this->getSumOwnHits() / $count;
		return $this->hitsTeamAvg;
	}

	public function getSumOwnHits(): int {
		if (isset($this->hitsTeamSum)) {
			return $this->hitsTeamSum;
		}
		$this->hitsTeamSum = array_sum($this->hitsOwn);
		return $this->hitsTeamSum;
	}

	public function getAverageOwnDeaths(): float {
		if (isset($this->deathsTeamAvg)) {
			return $this->deathsTeamAvg;
		}
		$count = count($this->deathsOwn);
		if ($count === 0) {
			return 0;
		}
		$this->deathsTeamAvg = $this->getSumOwnDeaths() / $count;
		return $this->deathsTeamAvg;
	}

	public function getSumOwnDeaths(): int {
		if (isset($this->deathsTeamSum)) {
			return $this->deathsTeamSum;
		}
		$this->deathsTeamSum = array_sum($this->deathsOwn);
		return $this->deathsTeamSum;
	}

	public function getAverageMisses(): float {
		if (isset($this->missAvg)) {
			return $this->missAvg;
		}
		$misses = $this->getMisses();
		$count = count($misses);
		if ($count === 0) {
			return 0.0;
		}
		$this->missAvg = $this->getSumMisses() / $count;
		return $this->missAvg;
	}

	/**
	 * @return int[]
	 */
	public function getMisses(): array {
		if (!empty($this->misses)) {
			return $this->misses;
		}
		foreach ($this->shots as $key => $count) {
			$this->misses[$key] = $count - ($this->hits[$key] ?? 0);
		}
		return $this->misses;
	}

	public function getSumMisses(): int {
		if (isset($this->missSum)) {
			return $this->missSum;
		}
		$this->missSum = array_sum($this->getMisses());
		return $this->missSum;
	}
}