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
use Dibi\Bridges\Tracy\Panel;
use Gettext\Loader\PoLoader;
use Gettext\Translations;
use Latte\Bridges\Tracy\BlueScreenPanel;
use Latte\Bridges\Tracy\LattePanel;
use Lsr\Core\App;
use Lsr\Core\DB;
use Lsr\Helpers\Tools\Timer;
use Lsr\Helpers\Tracy\CacheTracyPanel;
use Lsr\Helpers\Tracy\DbTracyPanel;
use Lsr\Helpers\Tracy\RoutingTracyPanel;
use Lsr\Helpers\Tracy\TimerTracyPanel;
use Lsr\Helpers\Tracy\TranslationTracyPanel;
use Nette\Bridges\DITracy\ContainerPanel;
use Nette\Bridges\HttpTracy\SessionPanel;
use Tracy\Debugger;

if (!defined('ROOT')) {
	define("ROOT", dirname(__DIR__) . '/');
}

date_default_timezone_set('Europe/Prague');

// Autoload libraries
require_once ROOT . 'vendor/autoload.php';

// Load all globals and constants
require_once ROOT . 'include/config.php';

Timer::start('core.init');

if (!is_dir(LOG_DIR) && !mkdir(LOG_DIR) && (!file_exists(LOG_DIR) || !is_dir(LOG_DIR))) {
	throw new RuntimeException(sprintf('Directory "%s" was not created', LOG_DIR));
}

// Enable tracy
Debugger::enable(PRODUCTION ? Debugger::PRODUCTION : Debugger::DEVELOPMENT, LOG_DIR);
Debugger::$editor = 'phpstorm://open?file=%file&line=%line';

Debugger::$dumpTheme = 'dark';

// Register custom tracy panels
Debugger::getBar()
        ->addPanel(new TimerTracyPanel())
        ->addPanel(new CacheTracyPanel())
        ->addPanel(new DbTracyPanel())
        ->addPanel(new TranslationTracyPanel())
        ->addPanel(new RoutingTracyPanel());

Loader::init();

define('CHECK_TRANSLATIONS', (bool)(App::getConfig()['General']['TRANSLATIONS'] ?? false));

if (defined('INDEX')) {
	// Register library tracy panels
	if (!isset($_ENV['noDb'])) {
		(new Panel())->register(DB::getConnection());
	}
	Debugger::getBar()
	        ->addPanel(new ContainerPanel(App::getContainer()))
	        ->addPanel(new LattePanel(App::getService('templating.latte.engine'))) // @phpstan-ignore-line
	        ->addPanel(new SessionPanel());
}

BlueScreenPanel::initialize();

// Translations update
$translationChange = false;
if (!PRODUCTION) {
	Timer::start('core.init.translations');
	$poLoader = new PoLoader();
	/** @var Translations[] $translations */
	$translations = [];

	$languages = App::getSupportedLanguages();
	foreach ($languages as $lang => $country) {
		$concatLang = $lang . '_' . $country;
		$path = LANGUAGE_DIR . '/' . $concatLang;
		if (!is_dir($path)) {
			continue;
		}
		$file = $path . '/LC_MESSAGES/' . LANGUAGE_FILE_NAME . '.po';
		$translations[$concatLang] = $poLoader->loadFile($file);
	}
	Timer::stop('core.init.translations');
}

Timer::stop('core.init');