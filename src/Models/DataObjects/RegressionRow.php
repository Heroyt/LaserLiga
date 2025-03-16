<?php
declare(strict_types=1);

namespace App\Models\DataObjects;

class RegressionRow
{
	public int       $teammates;
	public int       $enemies;
	public int       $game_length;
	public int|float $value;
}