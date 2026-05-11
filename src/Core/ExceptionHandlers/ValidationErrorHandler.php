<?php
declare(strict_types=1);

namespace App\Core\ExceptionHandlers;

use Lsr\Core\Http\ExceptionHandlerInterface;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Dto\Notice;
use Lsr\Enums\NoticeType;
use Lsr\Interfaces\ResponseFactoryInterface;
use Lsr\Interfaces\SessionInterface;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\ObjectValidation\Exceptions\ValidationMultiException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final readonly class ValidationErrorHandler implements ExceptionHandlerInterface
{
	public function __construct(
		private ResponseFactoryInterface $responseFactory,
		private SessionInterface $session,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function handles(Throwable $exception): bool {
		return $exception instanceof ValidationException;
	}

	/**
	 * @inheritDoc
	 */
	public function handle(Throwable $exception, Request $request): ResponseInterface {
		assert($exception instanceof ValidationException);

		$values = [];
		$this->addValidationErrors($values, $exception);

		$acceptTypes = array_filter(
			array_map(
				static fn(string $header) => strtolower(trim(explode(';', $header, 2)[0])),
				$request->getHeader('Accept')
			)
		);

		if ($request->isAjax() || in_array('application/json', $acceptTypes, true)) {
			return $this->responseFactory->createJsonResponse(
				new ErrorResponse(
					        'Invalid requests',
					        ErrorType::VALIDATION,
					values: $values,
				),
				400
			);
		}

		$referer = $request->getHeader('Referer');

		foreach ($values as $property => $error) {
			if (is_array($error)) {
				foreach ($error as $msg) {
					$this->session->flashNotice(
						new Notice(
							$msg,
							NoticeType::ERROR,
							lang('Chyba v poli %s', context: 'errors', format: [$property]),
						)
					);
				}
			}
			else {
				$this->session->flashNotice(
					new Notice(
						$error,
						NoticeType::ERROR,
						lang('Chyba v poli %s', context: 'errors', format: [$property]),
					)
				);
			}
		}

		return $this->responseFactory->createResponse(
			302,
			['Location' => $referer ? $referer[0] : '/']
		);
	}

	/**
	 * @param array<string,string|string[]> $values
	 */
	private function addValidationErrors(array &$values, ValidationException|ValidationMultiException $exception): void {
		if ($exception instanceof ValidationMultiException) {
			foreach ($exception->exceptions as $subException) {
				if ($subException instanceof ValidationMultiException) {
					$this->addValidationErrors($values, $subException);
					continue;
				}

				bdump($subException);

				if (isset($values[$subException->property])) {
					if (!is_array($values[$subException->property])) {
						$values[$subException->property] = [$values[$subException->property]];
					}
					assert(is_array($values[$subException->property]));
					$values[$subException->property][] = lang(
						         $subException->getMessage(),
						context: 'errors',
					);
				}
				else {
					$values[$subException->property] = lang(
						         $subException->getMessage(),
						context: 'errors',
					);
				}
			}
		}
		else {
			$values[$exception->property] = lang($exception->getMessage(), context: 'errors');
		}
	}
}