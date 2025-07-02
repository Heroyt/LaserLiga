<?php
declare(strict_types=1);

namespace App\Request\Admin\Arena;

class ArenaApiKeyRequest
{

	/** @var ArenaApiKeyKey[] */
	public array $key = [];

	public function addKey(ArenaApiKeyKey $key): void {
		$this->key[] = $key;
	}

}