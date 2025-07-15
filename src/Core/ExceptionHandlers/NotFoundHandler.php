<?php
declare(strict_types=1);

namespace App\Core\ExceptionHandlers;

use App\Controllers\E404;
use Lsr\Core\Http\ExceptionHandlerInterface;
use Lsr\Core\Requests\Exceptions\RouteNotFoundException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Exceptions\ModelNotFoundException as RoutingModelNotFoundException;
use Lsr\Orm\Exceptions\ModelNotFoundException as OrmModelNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final readonly class NotFoundHandler implements ExceptionHandlerInterface
{
	public function __construct(
		private E404 $controller,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function handles(Throwable $exception): bool {
		return $exception instanceof RouteNotFoundException
			|| $exception instanceof OrmModelNotFoundException
			|| $exception instanceof RoutingModelNotFoundException;
	}

	/**
	 * @inheritDoc
	 */
	public function handle(Throwable $exception, Request $request): ResponseInterface {
		$this->controller->init($request);
		return $this->controller->show($request, $exception);
	}
}