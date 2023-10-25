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

	public function getPercentile(DistributionParam $param, int $value, bool $rankableOnly = true): int {
		$query = $this->queryDistribution($param);
		if ($rankableOnly) {
			$query->onlyRankable();
		}

		return $query->getPercentile($value);
	}

}