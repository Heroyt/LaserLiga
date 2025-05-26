<?php
declare(strict_types=1);

namespace App\Core\Middleware;

use Lsr\Core\Routing\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class CacheControl implements Middleware
{

	public function __construct(
		private int $maxAge = 0,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		$response = $handler->handle($request);
		if ($this->maxAge <= 0) {
			return $response
				->withHeader('Cache-Control', 'no-cache, no-store')
				->withHeader('Pragma', 'no-cache');
		}
		return $response
			->withHeader('Cache-Control', 'public, max-age=' . $this->maxAge)
			->withHeader('Pragma', 'public')
			->withHeader('Expires', gmdate('D, d M Y H:i:s T', time() + $this->maxAge));
	}
}