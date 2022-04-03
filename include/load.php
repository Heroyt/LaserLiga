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
use App\Logging\Tracy\DbTracyPanel;
use App\Logging\Tracy\RoutingTracyPanel;
use App\Logging\Tracy\TranslationTracyPanel;
use Gettext\Loader\PoLoader;
use Tracy\Debugger;

if (!defined('ROOT')) {
	define("ROOT", dirname(__DIR__).'/');
}

date_default_timezone_set('Europe/Prague');

session_start();

// Autoload libraries
require_once ROOT.'vendor/autoload.php';

// Load all globals and constants
require_once ROOT.'include/config.php';


// Translations update
$translationChange = false;
if (!PRODUCTION) {
	$poLoader = new PoLoader();
	$translations = [];
	$languages = glob(LANGUAGE_DIR.'*');
	bdump($languages);
	foreach ($languages as $path) {
		if (!is_dir($path)) {
			continue;
		}
		$lang = str_replace(LANGUAGE_DIR, '', $path);
		$file = $path.'/LC_MESSAGES/translations.po';
		$translations[$lang] = $poLoader->loadFile($file);
	}
	bdump($translations);
}

if ((!file_exists(LOG_DIR) || !is_dir(LOG_DIR)) && !mkdir(LOG_DIR) && !is_dir(LOG_DIR)) {
	throw new RuntimeException(sprintf('Directory "%s" was not created', LOG_DIR));
}

Debugger::enable(PRODUCTION ? Debugger::PRODUCTION : Debugger::DEVELOPMENT, LOG_DIR);
Debugger::getBar()
				->addPanel(new DbTracyPanel())
				->addPanel(new TranslationTracyPanel())
				->addPanel(new RoutingTracyPanel());

Loader::init();

