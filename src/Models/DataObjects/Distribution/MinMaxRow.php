<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Distribution;

class MinMaxRow
{
	public int|float|null $min = null;
	public int|float|null $max = null;
}