<?php


namespace App\Core\Middleware;


use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Routing\Middleware;
use Lsr\Core\Routing\MiddlewareResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CSRFCheck implements Middleware
{
	use MiddlewareResponder;

	public function __construct(
		public readonly string $name = '',
	) {
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		$csrfName = empty($this->name) ? $request->getUri()->getPath() : $this->name;
		if (!formValid($csrfName)) {
			return $this->respond(
				$request,
				new ErrorResponse('Request expired', ErrorType::ACCESS, 'Try reloading the page.'),
			);
		}

		return $handler->handle($request);
	}

}
