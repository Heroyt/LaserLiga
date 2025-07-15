<?php
declare(strict_types=1);

namespace App\Core\ExceptionHandlers;

use Lsr\Core\Requests\Request;

trait WithAcceptTypes
{

	protected function getAcceptTypes(Request $request): array
	{
		return array_filter(
			array_map(
				static fn(string $header) => strtolower(trim(explode(';', $header, 2)[0])),
				$request->getHeader('Accept')
			)
		);
	}

}