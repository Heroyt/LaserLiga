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
use Latte\Bridges\Tracy\LattePanel;
use Lsr\Caching\Tracy\CacheTracyPanel;
use Lsr\Core\App;
use Lsr\Core\Tracy\RoutingTracyPanel;
use Lsr\Core\Tracy\TranslationTracyPanel;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Timer;
use Nette\Bridges\DITracy\ContainerPanel;
use Nette\Bridges\HttpTracy\SessionPanel;
use Nette\Mail\Mailer;
use Tracy\Bridges\Nette\MailSender;
use Tracy\Debugger;
use Tracy\Logger;

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
if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR) && (!file_exists(UPLOAD_DIR) || !is_dir(UPLOAD_DIR))) {
	throw new RuntimeException(sprintf('Directory "%s" was not created', UPLOAD_DIR));
}

// Enable tracy
Debugger::$editor = 'phpstorm://open?file=%file&line=%line';
Debugger::$dumpTheme = 'dark';

// Register custom tracy panels
Debugger::getBar()
//        ->addPanel(new TimerTracyPanel())
//        ->addPanel(new CacheTracyPanel())
//        ->addPanel(new DbTracyPanel())
        ->addPanel(new TranslationTracyPanel())
        ->addPanel(new RoutingTracyPanel());

Loader::init();

$config = App::getInstance()->config->getConfig();

$auth = App::getService('auth');
assert($auth instanceof \Lsr\Core\Auth\Services\Auth);
if (isset($_COOKIE['tracy-debug']) && $auth->loggedIn() && $auth->getLoggedIn()->hasRight('debug')) {
	Debugger::enable(Debugger::Development, LOG_DIR);
}

if (isset($config['ENV']['TRACY_MAIL']) && is_string($config['ENV']['TRACY_MAIL'])) {
	$logger = Debugger::getLogger();
	assert($logger instanceof Logger);
	$logger->email = (string) $config['ENV']['TRACY_MAIL'];
	$mailer = App::getService('mailer');
	assert($mailer instanceof Mailer);
	$logger->mailer = static function($message, string $email) use ($mailer, $logger) {
		$mailSender = new MailSender($mailer, $logger->email, App::getInstance()->getBaseUrl());
		$mailSender->send($message, $email);
	};
}

define('CHECK_TRANSLATIONS', (bool) ($config['General']['TRANSLATIONS'] ?? false));
define(
	'TRANSLATIONS_COMMENTS',
	(bool) ($config['General']['TRANSLATIONS_COMMENTS'] ?? false)
);

if (defined('INDEX') && PHP_SAPI !== 'cli') {
	// Register library tracy panels
	if (!isset($_ENV['noDb'])) {
		(new Panel())->register(DB::getConnection()->connection);
	}
	if (Debugger::isEnabled()) {
		Debugger::getBar()
				->addPanel(new CacheTracyPanel(App::getService('cache'))) // @phpstan-ignore-line
		        ->addPanel(new ContainerPanel(App::getContainer()))
		        ->addPanel(new LattePanel(App::getService('templating.latte.engine'))) // @phpstan-ignore-line
		        ->addPanel(new SessionPanel());
	}
}

Timer::stop('core.init');
