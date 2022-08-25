<?php
/**
 * @file      config.php
 * @brief     App configuration
 * @details   Contains all constants and settings
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */

use Lsr\Core\App;

require_once ROOT.'include/constants.php';
const DEFAULT_RESULTS_DIR = ROOT.'lmx/results/';

// Prevent IDE warnings about non-existent constant
if (!defined('JSON_THROW_ON_ERROR')) {
	define('JSON_THROW_ON_ERROR', 4194304);
}

/** If in production */
define('PRODUCTION', App::isProduction());
define('CHECK_TRANSLATIONS', (bool) (App::getConfig()['General']['TRANSLATIONS'] ?? false));

