<?php

namespace App\Core\Middleware;

use App\Models\Arena;
use Lsr\Core\Routing\Middleware;
use Lsr\Interfaces\RequestInterface;
use RuntimeException;

/**
 * Middleware for checking a valid API key
 *
 * It will return HTTP 401 or HTTP 400 error of failure.
 */
class ApiToken implements Middleware
{

	private static string $bearerToken;

	/**
	 * @return string
	 */
	public static function getBearerToken() : string {
		if (!isset(self::$bearerToken)) {
			$headers = apache_request_headers();
			if (empty($headers['Authorization'])) {
				throw new RuntimeException('Missing Authorization header.');
			}
			preg_match('/([a-zA-Z\d]+) (.*)/', $headers['Authorization'], $matches);
			$authMethod = strtolower($matches[1] ?? '');
			$authParams = trim($matches[2] ?? '');
			if ($authMethod !== 'bearer') {
				throw new RuntimeException('Unsupported authorization scheme.');
			}
			self::$bearerToken = $authParams;
		}
		return self::$bearerToken;
	}

	/**
	 * @inheritDoc
	 * @throws \JsonException
	 */
	public function handle(RequestInterface $request) : bool {
		$headers = apache_request_headers();
		if (empty($headers['Authorization'])) {
			http_response_code(401);
			header('Content-type: application/json');
			echo json_encode(['error' => 'Missing Authorization header'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			exit;
		}

		preg_match('/([a-zA-Z\d]+) (.*)/', $headers['Authorization'], $matches);
		$authMethod = strtolower($matches[1] ?? '');
		$authParams = trim($matches[2] ?? '');
		if ($authMethod !== 'bearer') {
			http_response_code(400);
			header('Content-type: application/json');
			echo json_encode(['error' => 'Unsupported authorization scheme.', 'supportedSchemes' => ['Bearer']], JSON_THROW_ON_ERROR);
			exit;
		}

		self::$bearerToken = $authParams;
		if (Arena::checkApiKey($authParams) !== null) {
			return true;
		}

		http_response_code(401);
		header('Content-type: application/json');
		echo json_encode(['error' => 'Invalid token.'], JSON_THROW_ON_ERROR);
		exit;
	}
}