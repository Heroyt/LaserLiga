<?php
/**
 * @file  config/services.php
 * @brief List of all DI container definition files
 */

$services = [
	ROOT . 'vendor/lsr/routing/services.neon',
	ROOT . 'vendor/lsr/logging/services.neon',
	ROOT . 'vendor/lsr/serializer/services.neon',
	ROOT . 'vendor/lsr/core/services.neon',
	ROOT . 'vendor/lsr/auth/services.neon',
	ROOT . 'config/constants.php',
];
$services[] = PRODUCTION ? ROOT . 'config/services.neon' : ROOT . 'config/servicesDebug.neon';
if (defined('PRIVATE_DIR') && file_exists(PRIVATE_DIR . 'config.neon')) {
	$services[] = PRIVATE_DIR . 'config.neon';
}
return $services;