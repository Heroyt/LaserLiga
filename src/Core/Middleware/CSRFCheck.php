<?php


namespace App\Core\Middleware;


use Lsr\Core\App;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Middleware;
use Lsr\Core\Routing\MiddlewareResponder;
use Lsr\Helpers\Csrf\TokenHelper;
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
		assert($request instanceof Request);
		$csrf = App::getServiceByType(TokenHelper::class);
		assert($csrf instanceof TokenHelper);
		$csrfName = empty($this->name) ? $request->getUri()->getPath() : $this->name;
		$token = $request->getPost('_csrf_token', '');
		if (!$csrf->formValid($csrfName, $token)) {
			return $this->respond(
				$request,
				new ErrorResponse('Request expired', ErrorType::ACCESS, 'Try reloading the page.'),
			);
		}

		return $handler->handle($request);
	}

}
