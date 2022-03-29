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

use App\Core\Controller;
use App\Core\DB;

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
class E404 extends Controller
{
	/**
	 * @var string $title Page name
	 */
	protected string $title = '404';
	/**
	 * @var string $description Page description
	 */
	protected string $description = 'Page not found';

	public function show() : void {
		DB::select('page_info', '*')->fetchAll();
		http_response_code(404);
		view('errors/E404', $this->params);
	}
}
