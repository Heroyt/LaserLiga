<?php
declare(strict_types=1);

namespace App\Exceptions;

use Lsr\Core\Requests\Response;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;

class DispatchBreakException extends \RuntimeException
{

	public function __construct(
		public ResponseInterface $response,
	) {
		parent::__construct('');
	}

	/**
	 * @param object|array<string|int,mixed>|string|resource $data
	 * @param int                          $code
	 * @param array<string,string>         $headers
	 *
	 * @return DispatchBreakException
	 */
	public static function create(
		mixed $data,
		int   $code = 200,
		array $headers = []
	): DispatchBreakException {
		$response = new Response(new \Nyholm\Psr7\Response($code, $headers));

		if (is_string($data)) {
			$response = $response->withStringBody($data);
		}
		else if (is_resource($data)) {
			$response = $response->withBody(new Stream($data));
		}
		else {
			$response = $response->withJsonBody($data);
		}

		return new self($response);
	}

	public function getResponse(): ResponseInterface {
		return $this->response;
	}

}