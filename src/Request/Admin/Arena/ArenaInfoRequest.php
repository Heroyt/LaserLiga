<?php
declare(strict_types=1);

namespace App\Request\Admin\Arena;

use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;

class ArenaInfoRequest
{

	#[Required, StringLength(min: 5)]
	public string $name;

	#[Required]
	public float $lat;

	#[Required]
	public float $lng;

}