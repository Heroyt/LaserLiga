<?php
declare(strict_types=1);

namespace App\Models;

interface WithSchema
{

	/**
	 * @return array<string,mixed>
	 */
	public function getSchema(): array;

}