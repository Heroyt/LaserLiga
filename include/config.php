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

use App\Core\App;

/** Directory containing log files */
const LOG_DIR = ROOT.'logs/';
/** Directory containing temporary files */
const TMP_DIR = ROOT.'temp/';
/** Directory containing template files */
const TEMPLATE_DIR = ROOT.'templates/';
/** Directory for user uploads */
const UPLOAD_DIR = ROOT.'upload/';
/** Directory for files hidden from the user */
const PRIVATE_DIR = ROOT.'private/';
const LANGUAGE_DIR = ROOT.'languages/';
const ASSETS_DIR = ROOT.'assets/';
/** App's default language */
const DEFAULT_LANGUAGE = 'cs';
/** Suffixes for language translations */
const LANGUAGE_SUFFIXES = [
	'cs' => 'CZ',
	'en' => 'US',
];

// Prevent IDE warnings about non-existent constant
if (!defined('JSON_THROW_ON_ERROR')) {
	define('JSON_THROW_ON_ERROR', 4194304);
}

/** If in production */
define('PRODUCTION', App::isProduction());
define('CHECK_TRANSLATIONS', (bool) (App::getConfig()['General']['TRANSLATIONS'] ?? false));

/**
 * @var $DEBUG
 * @brief All debug information
 */
$DEBUG = [
	'DB' => [],
];
