<?php
declare(strict_types=1);

namespace App\Request\Admin\Arena;

use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;

class ArenaApiKeyKey
{

	#[StringLength(max: 50)]
	public string $name;
	public string $key;
	#[Required]
	public int $id;

}