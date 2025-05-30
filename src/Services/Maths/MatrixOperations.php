<?php

namespace App\Services\Maths;

/**
 * Several matrix operation functions
 */
trait MatrixOperations
{

	/**
	 * @param array<int|float>[] $mat
	 *
	 * @return array<int|float>[]
	 */
	public function matTranspose(array $mat) : array {
		return (count($mat) === 1) ? array_chunk($mat[0], 1) : array_map(NULL, ...$mat);
	}

	/**
	 * @param array<int|float>[] $mat1
	 * @param array<int|float>[] $mat2
	 *
	 * @return array<int|float>[]
	 */
	public function matMultiply(array $mat1, array $mat2) : array {
		$result = [];
		$rows1 = count($mat1);
		$cols1 = count($mat1[0]);
		$cols2 = count($mat2[0]);

		for ($i = 0; $i < $rows1; $i++) {
			for ($j = 0; $j < $cols2; $j++) {
				$sum = 0;
				for ($k = 0; $k < $cols1; $k++) {
					$sum += $mat1[$i][$k] * $mat2[$k][$j];
				}
				$result[$i][$j] = $sum;
			}
		}

		return $result;
	}

	/**
	 * @param array<int|float>[] $matrix
	 *
	 * @return array<int|float>[]
	 */
	public function matInverse(array $matrix) : array {
		$n = count($matrix);
		$identity = $this->identityMatrix($n);

		for ($j = 0; $j < $n; $j++) {
			$divisor = $matrix[$j][$j];
			$matrix[$j][$j] = 1;

			if (((int) $divisor) !== 0) {
				for ($k = 0; $k < $n; $k++) {
					$matrix[$j][$k] /= $divisor;
					$identity[$j][$k] /= $divisor;
				}
			}

			for ($i = 0; $i < $n; $i++) {
				if ($i !== $j) {
					$factor = $matrix[$i][$j];

					for ($k = 0; $k < $n; $k++) {
						$matrix[$i][$k] -= $factor * $matrix[$j][$k];
						$identity[$i][$k] -= $factor * $identity[$j][$k];
					}
				}
			}
		}

		return $identity;
	}

	/**
	 * @param int $n
	 *
	 * @return int[][]
	 */
	public function identityMatrix(int $n) : array {
		$matrix = [];
		for ($i = 0; $i < $n; $i++) {
			for ($j = 0; $j < $n; $j++) {
				$matrix[$i][$j] = $i === $j ? 1 : 0;
			}
		}
		return $matrix;
	}

}