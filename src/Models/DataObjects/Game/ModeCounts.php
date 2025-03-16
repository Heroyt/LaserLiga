<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Game;

class ModeCounts
{
	public int     $count;
	public int     $id_mode;
	public string  $modeName;
	public string  $interval;
	public ?string $date = null;
}