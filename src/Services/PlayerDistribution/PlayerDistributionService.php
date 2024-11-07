<?php

namespace App\Services\PlayerDistribution;

class PlayerDistributionService
{

	/**
	 * @param DistributionParam $param
	 * @param int|null          $min
	 * @param int|null          $max
	 * @param int|null          $step
	 * @param bool              $rankableOnly
	 *
	 * @return array<string, int>
	 */
	public function getDistribution(DistributionParam $param, ?int $min = null, ?int $max = null, ?int $step = null, bool $rankableOnly = true): array {
		$query = $this->queryDistribution($param, $min, $max, $step);

		if ($rankableOnly) {
			$query->onlyRankable();
		}

		return $query->get();
	}

	public function queryDistribution(DistributionParam $param, ?int $min = null, ?int $max = null, ?int $step = null): PlayerDistributionQuery {
		return new PlayerDistributionQuery($param, $min, $max, $step);
	}

	/**
	 * @param DistributionParam $param
	 * @param int|float         $value
	 * @param bool              $rankableOnly
	 *
	 * @return int<1,99>
	 */
	public function getPercentile(DistributionParam $param, int|float $value, bool $rankableOnly = true): int {
		$query = $this->queryDistribution($param);
		if ($rankableOnly) {
			$query->onlyRankable();
		}

		return $query->getPercentile($value);
	}

}