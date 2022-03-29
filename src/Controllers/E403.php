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

use App\Core\Controller;

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


	public function show() : void {
		http_response_code(404);
		view('errors/E404', $this->params);
	}

}
