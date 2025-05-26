<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Player;

class AggregatedPlayerStats
{
	public ?float          $accuracy     = 0.0;
	public null|int|float      $hits         = 0;
	public null|int|float      $deaths       = 0;
	public ?float          $position     = 0.0;
	public ?float          $averageShots = 0.0;
	public null|int|float      $maxAccuracy  = 0;
	public null|int|float      $shots        = 0;
	public null|int|float $minutes      = null;
	public null|int|float      $maxScore     = 0;
	public null|int|float      $maxSkill     = 0;
}