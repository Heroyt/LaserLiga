<?php

namespace App\Core\Middleware;

use App\Exceptions\AuthHeaderException;
use App\Models\Arena;
use Lsr\Core\App;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Routing\Middleware;
use Lsr\Core\Routing\MiddlewareResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for checking a valid API key
 *
 * It will return HTTP 401 or HTTP 400 error of failure.
 */
class ApiToken implements Middleware
{
	use MiddlewareResponder;

	private static string $bearerToken;

	/**
	 * @return string
	 * @throws AuthHeaderException
	 */
	public static function getBearerToken(): string {
		if (!isset(self::$bearerToken)) {
			$request = App::getInstance()->getRequest();
			$auth = $request->getHeader('authorization');
			if (empty($auth)) {
				throw new AuthHeaderException('Missing Authorization header.');
			}
			preg_match('/([a-zA-Z\d]+) (.*)/', $auth[0], $matches);
			$authMethod = strtolower($matches[1] ?? '');
			$authParams = trim($matches[2] ?? '');
			if ($authMethod !== 'bearer') {
				throw new AuthHeaderException('Unsupported authorization scheme.');
			}
			self::$bearerToken = $authParams;
		}
		return self::$bearerToken;
	}

	/**
	 * @inheritDoc
	 * @throws \JsonException
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		$auth = $request->getHeader('authorization');
		if (empty($auth)) {
			return $this->respond($request, new ErrorResponse('Missing Authorization header', ErrorType::ACCESS));
		}

		preg_match('/([a-zA-Z\d]+) (.*)/', $auth[0], $matches);
		$authMethod = strtolower($matches[1] ?? '');
		$authParams = trim($matches[2] ?? '');
		if ($authMethod !== 'bearer') {
			return $this->respond(
				$request,
				new ErrorResponse(
					        'Unsupported authorization scheme.',
					        ErrorType::VALIDATION,
					values: ['supportedSchemes' => ['Bearer']]
				)
			);
		}

		self::$bearerToken = $authParams;
		if (Arena::checkApiKey($authParams) !== null) {
			return $handler->handle($request);
		}

		return $this->respond(
			$request,
			new ErrorResponse('Invalid token.', ErrorType::ACCESS, values: ['token' => $authParams]),
		);
	}
}