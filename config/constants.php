<?php
/**
 * @file  config/constants.php
 * @brief Constants that need to be imported into DI container
 */

use Lsr\Core\Config;

if (!defined('ROOT')) {
	define('ROOT', dirname(__DIR__).'/');
}

require_once ROOT.'include/constants.php';

return [
	'parameters' => [
		'constants' => [
			'debug' => (bool)(Config::getInstance()->getConfig('General')['DEBUG'] ?? false),
			'appDir'  => ROOT,
			'tempDir' => TMP_DIR,
		],
	],
];