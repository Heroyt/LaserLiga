<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Ranking;

use DateTimeInterface;

class PlayerGameRating
{
	public string $code;
	public int $id_user;
	public DateTimeInterface $date;
	public float $difference;
	public ?string $expected_results;
	public ?float $normalized_skill;
	public ?int $max_skill;
	public ?int $min_skill;
}