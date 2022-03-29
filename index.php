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

use App\Core\App;

/** Root directory */
const ROOT = __DIR__.'/';
/** Visiting site normally */
const INDEX = true;

require_once ROOT."include/load.php";

App::run();

updateTranslations();