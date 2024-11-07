<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Player;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'PlayerRadarValue', type: 'object')]
readonly class PlayerRadarValue
{
	/**
	 * @param int<1,99> $value           Percentile from 1 to 99
	 * @param string    $label           Label for the radar property
	 * @param string    $percentileLabel Percentile label to display (formatted value)
	 */
	public function __construct(
		#[OA\Property(description: 'Percentile from 1 to 99', maximum: 99, minimum: 1)]
		public int    $value,
		#[OA\Property(description: 'Label for the radar property', example: '10 výstřelů za minutu')]
		public string $label,
		#[OA\Property(description: 'Percentile label to display (formatted value)', example: 'Percentil: Nejlepších 10%')]
		public string $percentileLabel,
	) {
	}

	/**
	 * Create a new instance and automatically generate $percentileLabel from $value.
	 *
	 * @param int<1,99> $value Percentile from 1 to 99
	 * @param string    $label Label for the radar property
	 *
	 * @return PlayerRadarValue
	 */
	public static function createAutoLabel(int $value, string $label): PlayerRadarValue {
		$percentileLabel = lang('Percentil') . ': ';
		if ($value >= 50) {
			$percentileLabel .= lang(
				        'Nejlepší %d %%',
				        'Nejlepších %d %%',
				        $value >= 99 ? 1 : 100 - $value,
				format: [$value >= 99 ? 1 : 100 - $value]
			);
		}
		else {
			$percentileLabel .= lang(
				        'Nejhorší %d %%',
				        'Nejhorších %d %%',
				        $value,
				format: [$value],
			);
		}
		return new self($value, $label, $percentileLabel);
	}
}