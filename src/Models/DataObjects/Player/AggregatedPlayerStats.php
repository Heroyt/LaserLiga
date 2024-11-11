<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Player;

class AggregatedPlayerStats
{
	public float $accuracy = 0.0;
	public int $hits = 0;
	public int $deaths = 0;
	public float $position = 0.0;
	public float $averageShots = 0.0;
	public int $maxAccuracy = 0;
	public int $shots = 0;
	public ?int $minutes = null;
	public int $maxScore = 0;
	public int $maxSkill = 0;
}