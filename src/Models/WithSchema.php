<?php
declare(strict_types=1);

namespace App\Models;

interface WithSchema
{

	public function getSchema(): array;

}