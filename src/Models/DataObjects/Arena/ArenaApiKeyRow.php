<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Arena;

class ArenaApiKeyRow
{

	public int $id_key;
	public string $key;
	public ?string $name;

}