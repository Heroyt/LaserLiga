<?php
declare(strict_types=1);

namespace App\Core\Middleware;

use Lsr\Core\App;
use Lsr\Core\Routing\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ContentLanguageHeader implements Middleware
{

	/**
	 * @inheritDoc
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		return $handler->handle($request)->withAddedHeader('Content-Language', App::getInstance()->getLanguage()->id);
	}
}