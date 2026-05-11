<?php

/**
 * @file    load.php
 * @brief   Main bootstrap
 * @details File which is responsible for loading all necessary components of the app
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @date    2021-09-22
 * @version 1.0
 * @since   1.0
 */

use App\Core\Loader;

if (!defined('ROOT')) {
	define("ROOT", dirname(__DIR__) . '/');
}

date_default_timezone_set('Europe/Prague');

// Autoload libraries
require_once ROOT . 'vendor/autoload.php';

// Load all globals and constants
require_once ROOT . 'include/config.php';

if (!is_dir(LOG_DIR) && !mkdir(LOG_DIR) && (!file_exists(LOG_DIR) || !is_dir(LOG_DIR))) {
	throw new RuntimeException(sprintf('Directory "%s" was not created', LOG_DIR));
}

if (!is_dir(LOG_DIR.'tracy') && !mkdir(LOG_DIR.'tracy') && (!file_exists(LOG_DIR.'tracy') || !is_dir(LOG_DIR.'tracy'))) {
	throw new RuntimeException(sprintf('Directory "%s" was not created', LOG_DIR.'tracy'));
}
if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR) && (!file_exists(UPLOAD_DIR) || !is_dir(UPLOAD_DIR))) {
	throw new RuntimeException(sprintf('Directory "%s" was not created', UPLOAD_DIR));
}


Loader::init();

define('CHECK_TRANSLATIONS', (bool)($config['General']['TRANSLATIONS'] ?? false));
define('TRANSLATIONS_COMMENTS', (bool)($config['General']['TRANSLATIONS_COMMENTS'] ?? false));