<?php
/**
 * @file      E404.php
 * @brief     Pages\E404 class
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 *
 * @ingroup   Pages
 */

namespace App\Controllers;

use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * @class   E404
 * @brief   404 error page
 *
 * @package Pages
 * @ingroup Pages
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
class E400 extends Controller
{
	/**
	 * @var string $title Page name
	 */
	protected string $title = '400';
	/**
	 * @var string $description Page description
	 */
	protected string $description = 'Invalid request';

	public function show(?Request $request, ?Throwable $e = null): ResponseInterface {
		if (str_contains($request?->getHeaderLine('Accept'), 'application/json')) {
			return $this->respond(
				new ErrorResponse(
					           'Invalid request',
					type: ErrorType::NOT_FOUND,
					detail: $e?->getMessage(),
					exception: $e,
				),
				404
			);
		}
		$this->params['exception'] = $e;
		return $this->view('errors/E400')->withStatus(400);
	}
}
