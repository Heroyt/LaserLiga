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


use Lsr\Core\App;
use Lsr\Core\FpmHandler;
use Lsr\Helpers\Tools\Timer;

const ROOT = __DIR__ . '/';
/** Visiting site normally */
const INDEX = true;

session_cache_limiter('');
require_once ROOT . "include/load.php";

$app = App::getInstance();

Timer::start('app');

$fpmHandler = $app::getService('lsr.fpmHandler');
assert($fpmHandler instanceof FpmHandler);

$fpmHandler->run();