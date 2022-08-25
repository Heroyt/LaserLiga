<?php


namespace App\Core\Middleware;


use Lsr\Core\Routing\Middleware;
use Lsr\Interfaces\RequestInterface;

class CSRFCheck implements Middleware
{

	/**
	 * @param RequestInterface $request
	 *
	 * @return bool
	 */
	public function handle(RequestInterface $request) : bool {
		$csrfName = implode('/', $request->path);
		if (!formValid($csrfName)) {
			$error = lang('Požadavek vypršel, zkuste to znovu.', context: 'errors');
			$request->query['error'] = $error;
			$request->errors[] = $error;
			return false;
		}
		return true;
	}

}
