<?php
/**
 * @file      E403.php
 * @brief     Pages\E403 class
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

/**
 * @class   E403
 * @brief   403 error page
 *
 * @package Pages
 * @ingroup Pages
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
class E403 extends Controller
{
	/**
	 * @var string $title Page name
	 */
	protected string $title = '403';
	/**
	 * @var string $description Page description
	 */
	protected string $description = 'Access denied';


	public function show(Request $request, ?\Exception $e = null) : ResponseInterface {
		if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
			return $this->respond(
				new ErrorResponse(
					           'Access denied',
					type: ErrorType::ACCESS,
					detail: $e?->getMessage(),
					exception: $e,
				),
				403
			);
		}
		$this->params['exception'] = $e;
		return $this->view('errors/E403')->withStatus(403);
	}

}
