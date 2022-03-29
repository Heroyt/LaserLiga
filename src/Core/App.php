<?php
/**
 * @file      App.php
 * @brief     Core\App class
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */


namespace App\Core;


use App\Core\Auth\User;
use App\Core\Interfaces\RequestInterface;
use App\Core\Routing\Route;
use App\Exceptions\FileException;
use App\Logging\Logger;
use Gettext\Languages\Language;
use Latte\Engine;
use Latte\Macros\MacroSet;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\Http\Url;
use const PRIVATE_DIR;

/**
 * @class   App
 * @brief   App class containing all global getters and setters for app-wide options
 *
 * @package Core
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
class App
{
	/**
	 * @var Engine $latte Latte engine
	 */
	public static Engine    $latte;
	public static string    $activeLanguageCode = 'cs_CZ';
	public static ?Language $language;
	public static array     $supportedLanguages = [];
	public static array     $supportedCountries = [];
	/**
	 * @var bool $prettyUrl
	 * @brief If app should use a SEO-friendly pretty url
	 */
	protected static bool $prettyUrl = false;
	/** @var RequestInterface $request Current request object */
	protected static RequestInterface $request;
	protected static Logger           $logger;

	/** @var array Parsed config.ini file */
	protected static array $config;
	/**
	 * @var string
	 */
	private static mixed $timezone;

	private static Container $container;


	/**
	 * Initialization function
	 *
	 * @post Logger is initialized
	 * @post Routes are set
	 * @post Request is parsed
	 * @post Latte macros are set
	 */
	public static function init() : void {
		self::$logger = new Logger(LOG_DIR, 'app');
		self::setupRoutes();

		if (PHP_SAPI === "cli") {
			global $argv;
			self::$request = new CliRequest($argv[1] ?? '');
		}
		else {
			self::$request = new Request(self::$prettyUrl ? $_SERVER['REQUEST_URI'] : ($_GET['p'] ?? []));
		}

		$loader = new ContainerLoader(TMP_DIR);
		$class = $loader->load(function(Compiler $compiler) {
			$compiler->loadConfig(ROOT.'config/services.neon');
		});
		self::$container = new $class;

		// Set language and translations
		self::$language = Language::getById(self::getDesiredLanguageCode());
		date_default_timezone_set(self::getTimezone());
		if (isset(self::$language)) {
			$supported = self::getSupportedLanguages();
			self::$activeLanguageCode = self::$language->id;
			if (isset($supported[self::$language->id])) {
				self::$activeLanguageCode .= '_'.$supported[self::$language->id];
			}
			putenv('LANG='.self::$activeLanguageCode);
			putenv('LC_ALL='.self::$activeLanguageCode);
			setlocale(LC_ALL, [self::$activeLanguageCode.'.UTF-8', self::$activeLanguageCode, self::$language->name]);
			bindtextdomain("LAC", substr(LANGUAGE_DIR, 0, -1));
			textdomain('LAC');
			bind_textdomain_codeset('LAC', "UTF-8");
		}

		self::setupLatte();
	}

	/**
	 * Include all files from the /routes directory to initialize the Route objects
	 *
	 * @see Route
	 */
	protected static function setupRoutes() : void {
		$routeFiles = glob(ROOT.'routes/*.php');
		foreach ($routeFiles as $file) {
			require $file;
		}
	}

	/**
	 * Get desired language for the page
	 *
	 * Checks request parameters, session and HTTP headers in this order.
	 *
	 * @return string Language code
	 */
	protected static function getDesiredLanguageCode() : string {
		$request = self::getRequest();
		if (isset($request, $request->params['lang']) && self::isSupportedLanguage($request->params['lang'])) {
			return $request->params['lang'];
		}
		if (isset($_SESSION['lang']) && self::isSupportedLanguage($_SESSION['lang'])) {
			return $_SESSION['lang'];
		}
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			$info = explode(';', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
			$languages = explode(',', $info[0]);
			foreach ($languages as $language) {
				if (self::isSupportedLanguage($language)) {
					return $language;
				}
			}
		}
		return DEFAULT_LANGUAGE;
	}

	/**
	 * Get the request array
	 *
	 * @return Request|null
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function getRequest() : ?RequestInterface {
		return self::$request ?? null;
	}

	/**
	 * Test if the language code is valid and if the language is supported
	 *
	 * @param string $language Language code
	 *
	 * @return bool
	 */
	protected static function isSupportedLanguage(string $language) : bool {
		preg_match('/([a-z]{2})[\-_]?/', $language, $matches);
		$id = $matches[1];
		return self::isValidLanguage($language) && isset(self::getSupportedLanguages()[$id]);
	}

	/**
	 * Check if the language exists
	 *
	 * @param string $language
	 *
	 * @return bool
	 */
	protected static function isValidLanguage(string $language) : bool {
		return Language::getById($language) !== null;
	}

	/**
	 * @param bool $returnObjects
	 *
	 * @return string[]|Language[]
	 */
	public static function getSupportedLanguages(bool $returnObjects = false) : array {
		if (empty(self::$supportedLanguages)) {
			$dirs = array_map(static function(string $dir) {
				return str_replace(LANGUAGE_DIR, '', $dir);
			}, glob(LANGUAGE_DIR.'*'));
			foreach ($dirs as $dir) {
				[$lang, $country] = explode('_', $dir);
				self::$supportedLanguages[$lang] = $country;
			}
		}
		if ($returnObjects) {
			$return = [];
			foreach (self::$supportedLanguages as $lang => $country) {
				$return[$lang] = Language::getById($lang);
			}
			return $return;
		}
		return self::$supportedLanguages;
	}

	/**
	 * @return string
	 */
	public static function getTimezone() : string {
		if (empty(self::$timezone)) {
			self::$timezone = self::getConfig()['General']['TIMEZONE'] ?? 'Europe/Prague';
		}
		return self::$timezone;
	}

	/**
	 * Get parsed config.ini file
	 *
	 * @return array
	 */
	public static function getConfig() : array {
		if (!isset(self::$config)) {
			self::$config = parse_ini_file(PRIVATE_DIR.'config.ini', true);
		}
		return self::$config;
	}

	/**
	 * Setup all latte tags, filters and engine
	 */
	protected static function setupLatte() : void {
		self::$latte = new Engine();
		self::$latte->setTempDirectory(TMP_DIR.'latte/');
		$set = new MacroSet(self::$latte->getCompiler());
		$config = include ROOT.'config/latte.php';
		foreach ($config['tags'] ?? [] as $name => $args) {
			$set->addMacro($name, ...$args);
		}
		foreach ($config['filters'] ?? [] as $name => $callback) {
			self::$latte->addFilter($name, $callback);
		}
	}

	public static function getSupportedCountries() : array {
		if (empty(self::$supportedCountries)) {
			foreach (self::getSupportedLanguages() as $lang => $country) {
				if (isset(Constants::COUNTRIES[$country])) {
					self::$supportedCountries[$country] = Constants::COUNTRIES[$country];
				}
			}
		}
		return self::$supportedCountries;
	}

	/**
	 * Set pretty url to false
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function uglyUrl() : void {
		self::$prettyUrl = false;
	}

	/**
	 * Set pretty url to true
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function prettyUrl() : void {
		self::$prettyUrl = true;
	}

	/**
	 * Get all css files in dist and return html links
	 *
	 * @return string
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function getCss() : string {
		$files = glob(ROOT.'dist/*.css');
		$return = '';
		foreach ($files as $file) {
			if (!str_contains($file, '.min') && in_array(str_replace('.css', '.min.css', $file), $files, true)) {
				continue;
			}
			$return .= '<link rel="stylesheet" href="'.str_replace(ROOT, self::getUrl(), $file).'?v='.self::getCacheVersion().'" />'.PHP_EOL;
		}
		return $return;
	}

	/**
	 * Get the current URL
	 *
	 * @param bool $returnObject If true, return Url object, else return string
	 *
	 * @return Url|string
	 */
	public static function getUrl(bool $returnObject = false) : Url|string {
		$url = new Url();
		$url
			->setScheme(self::isSecure() ? 'https' : 'http')
			->setHost($_SERVER['HTTP_HOST'] ?? 'localhost');
		if ($returnObject) {
			return $url;
		}
		return (string) $url;
	}

	/**
	 * Get if https is enabled
	 *
	 * @return bool
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function isSecure() : bool {
		return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
	}

	/**
	 * Gets the FE cache version from config.ini
	 *
	 * @return int
	 */
	public static function getCacheVersion() : int {
		return (int) (self::getconfig()['General']['CACHE_VERSION'] ?? 1);
	}

	/**
	 * Get all js files in dist and return html script-src tags
	 *
	 * @return string
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function getJs() : string {
		$files = glob(ROOT.'dist/*.js');
		$return = '';
		foreach ($files as $file) {
			if (!str_contains($file, '.min') && in_array(str_replace('.js', '.min.js', $file), $files, true)) {
				continue;
			}
			$return .= '<script src="'.str_replace(ROOT, self::getUrl(), $file).'?v='.self::getCacheVersion().'"></script>'.PHP_EOL;
		}
		return $return;
	}

	/**
	 * Get current page HTML or run CLI command
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function run() : void {
		self::$request->handle();
	}

	/**
	 * Echo json-encoded data and exits
	 *
	 * @param array $data
	 */
	public static function sendAjaxData(array $data) : void {
		header('Content-Type: application/json; charset=UTF-8');
		bdump($data);
		exit(json_encode($data, JSON_THROW_ON_ERROR));
	}

	/**
	 * Checks if the GENERAL - DEBUG option is set in config.ini
	 *
	 * @return bool
	 */
	public static function isProduction() : bool {
		return !(bool) (self::getconfig()['General']['DEBUG'] ?? false);
	}

	/**
	 * Redirect to something
	 *
	 * @param string[]|string|Route|Url $to
	 * @param Request|null              $from
	 *
	 * @noreturn
	 */
	public static function redirect(Url|Route|array|string $to, ?Request $from = null) : void {
		$link = '';
		if ($to instanceof Route) {
			$link = self::getLink($to->path);
		}
		elseif ($to instanceof Url) {
			$link = $to->getAbsoluteUrl();
		}
		elseif (is_array($to)) {
			$link = self::getLink($to);
		}
		elseif (is_string($to)) {
			$route = Route::getRouteByName($to);
			if (isset($route)) {
				$link = self::getLink($route->path);
			}
			else {
				$link = $to;
			}
		}
		if (isset($from) && $from instanceof Request) {
			$_SESSION['fromRequest'] = serialize($from);
		}
		header('Location: '.$link);
		exit;
	}

	/**
	 * Get url to request location
	 *
	 * @param array $request      request array
	 *                            * Ex: ['user', 'login', 'view' => 1, 'type' => 'company']: http(s)://host.cz/user/login?view=1&type=company
	 * @param bool  $returnObject if set to true, return Url object
	 *
	 * @return string|Url
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function getLink(array $request = [], bool $returnObject = false) : Url|string {
		$url = self::getUrl(true);
		$request = array_filter($request, static function($value) {
			return !empty($value);
		});
		if (self::isPrettyUrl()) {
			$url->setPath(implode('/', array_filter($request, 'is_int', ARRAY_FILTER_USE_KEY)));
			$url->setQuery(array_filter($request, 'is_string', ARRAY_FILTER_USE_KEY));
		}
		else {
			$query = array_filter($request, 'is_string', ARRAY_FILTER_USE_KEY);
			$query['p'] = array_filter($request, 'is_int', ARRAY_FILTER_USE_KEY);
			$url->setQuery($query);
		}
		if ($returnObject) {
			return $url;
		}
		return (string) $url;
	}

	/**
	 * Get prettyUrl
	 *
	 * @return bool
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function isPrettyUrl() : bool {
		return self::$prettyUrl;
	}

	/**
	 * @return Logger
	 */
	public static function getLogger() : Logger {
		if (!isset(self::$logger)) {
			self::$logger = new Logger(LOG_DIR, 'app');
		}
		return self::$logger;
	}

	/**
	 * @return Container
	 */
	public static function getContainer() : Container {
		return self::$container;
	}

	/**
	 * @param string $type
	 *
	 * @return MenuItem[]
	 * @throws FileException
	 */
	public static function getMenu(string $type = 'menu') : array {
		if (!file_exists(ROOT.'config/nav/'.$type.'.php')) {
			throw new FileException('Menu configuration file "'.$type.'.php" does not exist.');
		}
		$config = require ROOT.'config/nav/'.$type.'.php';
		$menu = [];
		foreach ($config as $item) {
			if (!self::checkAccess($item)) {
				continue;
			}
			if (isset($item['route'])) {
				$path = Route::getRouteByName($item['route'])->path;
			}
			else {
				$path = $item['path'] ?? ['E404'];
			}
			$menuItem = new MenuItem(name: $item['name'], icon: $item['icon'] ?? '', path: $path);
			foreach ($item['children'] ?? [] as $child) {
				if (!self::checkAccess($child)) {
					continue;
				}
				if (isset($child['route'])) {
					$path = Route::getRouteByName($child['route'])->path;
				}
				else {
					$path = $child['path'] ?? ['E404'];
				}
				$menuItem->children[] = new MenuItem(name: $child['name'], icon: $child['icon'] ?? '', path: $path);
			}
			$menu[] = $menuItem;
		}
		return $menu;
	}

	/**
	 * @param array{access:array|null|string,loggedInOnly:bool|null,loggedOutOnly:bool|null} $item
	 *
	 * @return bool
	 */
	private static function checkAccess(array $item) : bool {
		if (isset($item['loggedInOnly']) && $item['loggedInOnly'] && !User::loggedIn()) {
			return false;
		}
		if (isset($item['loggedOutOnly']) && $item['loggedOutOnly'] && User::loggedIn()) {
			return false;
		}
		if (!isset($item['access'])) {
			return true;
		}
		$available = true;
		$access = [];
		if (is_string($item['access'])) {
			$access = [$item['access']];
		}
		else if (is_array($item['access'])) {
			$access = $item['access'];
		}
		foreach ($access as $right) {
			if (!User::hasRight($right)) {
				$available = false;
				break;
			}
		}
		return $available;
	}

	public static function comparePaths(array $path1, ?array $path2 = null) : bool {
		if (!isset($path2)) {
			$path2 = self::getRequest()->path;
		}
		foreach ($path1 as $key => $value) {
			if (!is_numeric($key)) {
				unset($path1[$key]);
			}
			else {
				$path1[$key] = strtolower($value);
			}
		}
		foreach ($path2 as $key => $value) {
			if (!is_numeric($key)) {
				unset($path2[$key]);
			}
			else {
				$path2[$key] = strtolower($value);
			}
		}
		return $path1 === $path2;
	}

	public static function getShortLanguageCode() : string {
		return explode('_', self::$activeLanguageCode)[0];
	}

}
