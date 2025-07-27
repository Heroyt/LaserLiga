<?php

namespace App\Core\Middleware;

use Lsr\Core\App;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Middleware;
use Lsr\Core\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for checking if a user is in kiosk mode
 */
class StartKioskSession implements Middleware
{

	/**
	 * @inheritDoc
	 * @throws \JsonException
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		$session = App::getService('session');
		assert($session instanceof Session);

		$session->set('kiosk', true);
		if ($request instanceof Request) {
			$arenaId = $request->getParam('arena');
			if ($arenaId !== null) {
				$session->set('kioskArena', (int)$arenaId);
			}
		}

		return $handler->handle($request);
	}
}