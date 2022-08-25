<?php
/**
 * @file      index.php
 * @brief     Main php file accessed by user
 * @details   All user connections are directed here
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */

use App\Controllers\E404;
use Lsr\Core\App;
use Lsr\Core\Requests\Exceptions\RouteNotFoundException;
use Lsr\Helpers\Tools\Timer;

/** Root directory */
const ROOT = __DIR__.'/';
/** Visiting site normally */
const INDEX = true;

require_once ROOT."include/load.php";

Timer::start('app');
try {
	App::run();
} catch (RouteNotFoundException $e) {
	// Handle 404 Error
	$controller = App::getContainer()->getByType(E404::class);
	$controller->init(App::getRequest());
	$controller->show(App::getRequest());
}
Timer::stop('app');

updateTranslations();