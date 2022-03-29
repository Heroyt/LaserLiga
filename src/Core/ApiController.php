<?php

namespace App\Core;


abstract class ApiController extends Controller
{

	/**
	 * @param string|array|object $data
	 * @param int                 $code
	 * @param string[]            $headers
	 *
	 * @return never
	 */
	public function respond(string|array|object $data, int $code = 200, array $headers = []) : never {
		http_response_code($code);

		$dataNormalized = '';
		if (is_string($data)) {
			$dataNormalized = $data;
		}
		else if (is_array($data) || is_object($data)) {
			$dataNormalized = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			$headers['Content-Type'] = 'application/json';
		}


		foreach ($headers as $name => $value) {
			header($name.': '.$value);
		}

		echo $dataNormalized;
		exit;
	}

}