<?php
/**
 * @file      install.php
 * @brief     Script to install and seed DB.
 * @author    Tomáš Vojík <vojik@wboy.cz>
 */

/** Root directory */

use App\Install\Install;

const ROOT = __DIR__.'/';
/** Visiting site normally */
const INDEX = true;

array_unshift($argv, 'install');

require_once ROOT."include/load.php";

if (Install::install(in_array('fresh', $argv ?? [], true) || isset($_GET['fresh']))) {
	echo 'Successfully installed!'.PHP_EOL;
	exit(0);
}
echo 'Installation failed.'.PHP_EOL;
exit(1);