<?php
/**
 * @file  config/services.php
 * @brief List of all DI container definition files
 */

use Lsr\Core\App;

$services = [
	ROOT.'vendor/lsr/routing/services.neon',
	ROOT.'vendor/lsr/logging/services.neon',
	ROOT.'vendor/lsr/core/services.neon',
];
$services[] = App::isProduction() ? ROOT.'config/services.neon' : ROOT.'config/servicesDebug.neon';
return $services;