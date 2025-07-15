<?php
declare(strict_types=1);

namespace App\Core\ExceptionHandlers;

use Lsr\Core\Http\ExceptionHandlerInterface;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Exceptions\AccessDeniedException;
use Lsr\Interfaces\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class AccessDeniedHandler implements ExceptionHandlerInterface
{
	use WithAcceptTypes;

	public function __construct(
		private ResponseFactoryInterface $responseFactory,
	) {}

	/**
	 * @inheritDoc
	 */
	public function handles(\Throwable $exception): bool {
		return $exception instanceof AccessDeniedException;
	}

	/**
	 * @inheritDoc
	 */
	public function handle(\Throwable $exception, Request $request): ResponseInterface {
		$acceptTypes = $this->getAcceptTypes($request);

		if (in_array('application/json', $acceptTypes, true)) {
			return $request->responseFactory->createJsonResponse(
				new ErrorResponse(
					'Forbidden',
					ErrorType::ACCESS,
					detail: $exception->getMessage(),
					exception: $exception,
				),
				403,
				['Content-Type' => 'application/json']
			);
		}

		return $request->responseFactory->createResponse(
			403,
			['Content-Type' => 'text/plain'],
			null,
			'1.1',
			'Forbidden'
		);
	}
}