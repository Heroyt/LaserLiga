<?php

namespace App\Services\Maths;

/**
 * Service for calculating regression
 */
class RegressionCalculator
{

	use MatrixOperations;

	/**
	 * Calculate prediction value based on a regression model
	 *
	 * @param array<int|float> $inputs Input values in order without the constant 1
	 * @param array<int|float> $model  Calculated regression model coefficients. First argument should be the model constant.
	 *
	 * @return float
	 */
	public static function calculateRegressionPrediction(array $inputs, array $model) : float {
		// Coefficient order: $in[0]*coeff[1] + $in[1]*$coeff[2] + $in[2]*$coeff[3]... + $in[0] * $in[1] * $coeff[x] + ... ($in[0] ^ 2) * $coeff[y] + ...
		$coefficientCount = count($model);
		$inputCount = count($inputs);

		// First coefficient is a constant
		$prediction = $model[0];
		$i = 1;

		// Linear coefficients
		foreach ($inputs as $input) {
			$prediction += $model[$i] * $input;
			$i++;
			if ($i >= $coefficientCount) {
				break;
			}
		}

		if ($coefficientCount > $i) {
			// Multiplication coefficients
			foreach ($inputs as $j => $input) {
				for ($k = ($j + 1); $k < $inputCount; $k++) {
					$prediction += $model[$i] * $input * $inputs[$k];
					$i++;
					if ($i >= $coefficientCount) {
						break;
					}
				}
				if ($i >= $coefficientCount) {
					break;
				}
			}
		}

		if ($coefficientCount > $i) {
			// Squared coefficients
			foreach ($inputs as $input) {
				$prediction += $model[$i] * ($input ** 2);
				$i++;
				if ($i >= $coefficientCount) {
					break;
				}
			}
		}

		return (float) $prediction;
	}

	/**
	 * @param array<int|float>[] $matF Inputs
	 * @param array<int|float>[] $matY Outputs
	 *
	 * @return array<int|float>
	 */
	public function regression(array $matF, array $matY) : array {
		// Calculate simple linear model
		$matFT = $this->matTranspose($matF);
		$matG = $this->matMultiply($matFT, $matY);
		$matH = $this->matMultiply($matFT, $matF);
		$matB = $this->matMultiply($this->matInverse($matH), $matG);

		return array_map(static fn(array $row) => $row[0], $matB);
	}

	/**
	 * @param array<int|float>[] $inputs
	 * @param array<int|float>   $model
	 *
	 * @return array<int|float>
	 */
	public function calculatePredictions(array $inputs, array $model) : array {
		$predictions = [];
		foreach ($inputs as $input) {
			$value = 0.0;
			foreach ($input as $key => $val) {
				$value += $model[$key] * $val;
			}
			$predictions[] = $value;
		}
		return $predictions;
	}

	/**
	 * @param array<int|float> $predictions
	 * @param array<int|float> $actual
	 *
	 * @return float
	 */
	public function calculateRSquared(array $predictions, array $actual) : float {
		$count = count($actual);
		$mean = array_sum($actual) / $count;
		$sst = 0;
		$ssr = 0;

		for ($i = 0; $i < $count; $i++) {
			$sst += ($actual[$i] - $mean) ** 2;
			$ssr += ($actual[$i] - $predictions[$i]) ** 2;
		}

		return ((int) ($sst)) === 0 ? 1 : 1 - ($ssr / $sst);
	}

}