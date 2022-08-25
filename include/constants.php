<?php
/**
 * @file      constants.php
 * @brief     All basic global constants
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @version   1.1
 * @since     1.1
 */

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
const LANGUAGE_FILE_NAME = 'translations';
const ASSETS_DIR = ROOT.'assets/';
/** App's default language */
const DEFAULT_LANGUAGE = 'cs';
/** Suffixes for language translations */
const LANGUAGE_SUFFIXES = [
	'cs' => 'CZ',
	'en' => 'US',
];

const EVENT_PORT = 9999;