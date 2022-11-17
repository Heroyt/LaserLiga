<?php

namespace App\Models\Group;

trait PlayerAggregate
{
	/** @var string[] */
	public array $gameCodes = [];

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
	protected float $hitsAvg;

	protected int   $deathsSum;
	protected float $deathsAvg;

	protected int   $shotsSum;
	protected float $shotsAvg;

	protected float $kdAvg;

	protected int $accuracyAvg;

	protected int|string $favouriteVest;

	public function getSumShots() : int {
		if (isset($this->shotsSum)) {
			return $this->shotsSum;
		}
		$this->shotsSum = array_sum($this->shots);
		return $this->shotsSum;
	}

	public function getAverageShots() : float {
		if (isset($this->shotsAvg)) {
			return $this->shotsAvg;
		}
		if (count($this->shots) === 0) {
			return 0;
		}
		$this->shotsAvg = array_sum($this->shots) / count($this->shots);
		return $this->shotsAvg;
	}

	public function getAverageAccuracy() : float {
		if (isset($this->accuracyAvg)) {
			return $this->accuracyAvg;
		}
		if (count($this->accuracies) === 0) {
			return 0;
		}
		$this->accuracyAvg = (int) round(array_sum($this->accuracies) / count($this->accuracies));
		return $this->accuracyAvg;
	}

	public function getAverageHits() : float {
		if (isset($this->hitsAvg)) {
			return $this->hitsAvg;
		}
		if (count($this->hits) === 0) {
			return 0;
		}
		$this->hitsAvg = array_sum($this->hits) / count($this->hits);
		return $this->hitsAvg;
	}

	public function getAverageDeaths() : float {
		if (isset($this->deathsAvg)) {
			return $this->deathsAvg;
		}
		if (count($this->deaths) === 0) {
			return 0;
		}
		$this->deathsAvg = array_sum($this->deaths) / count($this->deaths);
		return $this->deathsAvg;
	}

	public function getSumScore() : int {
		if (isset($this->scoreSum)) {
			return $this->scoreSum;
		}
		$this->scoreSum = array_sum($this->scores);
		return $this->scoreSum;
	}

	public function getAverageScore() : float {
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
	public function getSkill() : int {
		if (isset($this->skillAvg)) {
			return $this->skillAvg;
		}
		if (count($this->skills) === 0) {
			return 0;
		}
		$this->skillAvg = (int) round(array_sum($this->skills) / count($this->skills));
		return $this->skillAvg;
	}

	public function getFavouriteVest() : int|string {
		if (isset($this->favouriteVest)) {
			return $this->favouriteVest;
		}
		arsort($this->vests);
		$this->favouriteVest = array_key_first($this->vests) ?? 1;
		return $this->favouriteVest;
	}

	public function getKd() : float {
		if (isset($this->kdAvg)) {
			return $this->kdAvg;
		}
		$this->kdAvg = $this->getSumHits() / ($this->getSumDeaths() === 0 ? 1 : $this->getSumDeaths());
		return $this->kdAvg;
	}

	public function getSumHits() : int {
		if (isset($this->hitsSum)) {
			return $this->hitsSum;
		}
		$this->hitsSum = array_sum($this->hits);
		return $this->hitsSum;
	}

	public function getSumDeaths() : int {
		if (isset($this->deathsSum)) {
			return $this->deathsSum;
		}
		$this->deathsSum = array_sum($this->deaths);
		return $this->deathsSum;
	}
}