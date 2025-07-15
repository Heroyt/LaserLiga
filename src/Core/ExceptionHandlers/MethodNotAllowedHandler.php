<?php
declare(strict_types=1);

namespace App\Core\ExceptionHandlers;

use Lsr\Core\Http\ExceptionHandlerInterface;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Exceptions\MethodNotAllowedException;
use Lsr\Interfaces\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class MethodNotAllowedHandler implements ExceptionHandlerInterface
{
	use WithAcceptTypes;

	public function __construct(
		private ResponseFactoryInterface $responseFactory,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function handles(\Throwable $exception): bool {
		return $exception instanceof MethodNotAllowedException;
	}

	/**
	 * @inheritDoc
	 */
	public function handle(\Throwable $exception, Request $request): ResponseInterface {
		$acceptTypes = $this->getAcceptTypes($request);

		if (in_array('application/json', $acceptTypes, true)) {
			return $this->responseFactory->createJsonResponse(
				new ErrorResponse(
					'MethodNotAllowed',
					ErrorType::NOT_FOUND,
					exception: $exception,
				),
				405,
				['Content-Type' => 'application/json']
			);
		}

		return $this->responseFactory->createResponse(
			405,
			['Content-Type' => 'text/plain'],
			null,
			'1.1',
			'Method Not Allowed'
		);
	}
}