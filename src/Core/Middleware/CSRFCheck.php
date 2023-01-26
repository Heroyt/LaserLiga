<?php


namespace App\Core\Middleware;


use Lsr\Core\Routing\Middleware;
use Lsr\Interfaces\RequestInterface;

class CSRFCheck implements Middleware
{

	public function __construct(
		public readonly string $name = '',
	) {
	}

	/**
	 * @param RequestInterface $request
	 *
	 * @return bool
	 */
	public function handle(RequestInterface $request) : bool {
		$csrfName = empty($this->name) ? implode('/', $request->getPath()) : $this->name;
		if (!formValid($csrfName)) {
			$error = lang('Požadavek vypršel, zkuste to znovu.', context: 'errors');
			$request->addError($error);
			return false;
		}
		return true;
	}

}
