<?php
declare(strict_types=1);

namespace App\Core\ExceptionHandlers;

use Lsr\Core\Http\ExceptionHandlerInterface;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Request;
use Lsr\Interfaces\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Tracy\Debugger;
use Tracy\Helpers;
use Tracy\ILogger;

class TracyExceptionHandler implements ExceptionHandlerInterface
{
	public function __construct(
	private ResponseFactoryInterface $responseFactory,
) {}

	/**
	 * @inheritDoc
	 */
	public function handles(\Throwable $exception) : bool {
		return true; // Handles any exception
	}

	/**
	 * @param  \Throwable  $exception
	 * @param  Request  $request
	 * @inheritDoc
	 */
	public function handle(\Throwable $exception, Request $request) : ResponseInterface {
		Helpers::improveException($exception);
		Debugger::log($exception, ILogger::EXCEPTION);

		if (Debugger::isEnabled()) {
			ob_start(); // double buffer prevents sending HTTP headers in some PHP
			ob_start();
			Debugger::getBlueScreen()->render($exception);
			/** @var string $blueScreen */
			$blueScreen = ob_get_clean();
			ob_end_clean();

			return $this->responseFactory->createResponse(
				500,
				[
					'Content-Type' => 'text/html',
				],
				$blueScreen
			);
		}

		$acceptTypes = array_filter(
			array_map(
				static fn(string $header) => strtolower(trim(explode(';', $header, 2)[0])),
				$request->getHeader('Accept')
			)
		);

		if (in_array('application/json', $acceptTypes, true)) {
			try {
				$data = json_encode(
					new ErrorResponse(
						           'Something Went wrong!',
						detail   : $exception->getMessage(),
						exception: $exception
					),
					JSON_THROW_ON_ERROR
				);
			} catch (\JsonException) {
				$data = '{"type":"internal","title":"Something Went wrong!"}';
			}
			return $this->responseFactory->createResponse(
				500,
				['Content-Type' => 'application/json'],
				$data
			);
		}

		if (in_array('text/html', $acceptTypes, true)) {
			return $this->responseFactory->createResponse(
				500,
				['Content-Type' => 'text/html'],
				<<<HTML
                <!doctype html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                     <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
                     <meta http-equiv="X-UA-Compatible" content="ie=edge">
                     <title>Internal server error</title>
                </head>
                <body>
                  <h1>Something went wrong</h1>
                  <p>{$exception->getMessage()}</p>
                </body>
                </html>
                HTML
			);
		}

		return $this->responseFactory->createResponse(
			500,
			['Content-Type' => 'text/plain'],
			'Internal server error - '.$exception->getMessage()
		);
	}
}