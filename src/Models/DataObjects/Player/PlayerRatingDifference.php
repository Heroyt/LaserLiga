<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Player;

class PlayerRatingDifference
{
	public \DateTimeInterface $date;
	public float $difference;
}