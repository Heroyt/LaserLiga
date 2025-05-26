<?php
declare(strict_types=1);

namespace App\Core\Middleware;

use Lsr\Core\Routing\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class NoCacheControl implements Middleware
{

	/**
	 * @inheritDoc
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		return $handler->handle($request)
			->withHeader('Cache-Control', 'no-cache, no-store')
			->withHeader('Pragma', 'no-cache');
	}
}