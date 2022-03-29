<?php


namespace App\Core\Routing;


use App\Core\Interfaces\ControllerInterface;
use App\Core\Interfaces\RequestInterface;
use App\Core\Request;

class CliRoute implements RouteInterface
{

	/** @var Route[] Structure holding all set routes */
	public static array $availableRoutes = [];
	/** @var string[] Current URL path as an array (exploded using the "/") */
	public array $path = [];

	/** @var callable|array $handler Route callback */
	protected $handler;

	/** @var string Route's usage to print */
	public string $usage = '';
	/** @var string Route's description to print */
	public string $description = '';
	/** @var callable|null Command's help information to print */
	public $helpPrint = null;
	/** @var array{name:string,isOptional:bool,description:string,suggestions:array}[] */
	public array $arguments = [];


	/**
	 * Route constructor.
	 *
	 * @param string         $type
	 * @param callable|array $handler
	 */
	public function __construct(protected string $type, callable|array $handler) {
		$this->handler = $handler;
	}

	/**
	 * Create a new GET route
	 *
	 * @param string         $pathString path
	 * @param callable|array $handler    callback
	 *
	 * @return CliRoute
	 */
	public static function cli(string $pathString, callable|array $handler) : CliRoute {
		return self::create(self::CLI, $pathString, $handler);
	}

	/**
	 * Create a new route
	 *
	 * @param string         $type       [GET, POST, DELETE, PUT]
	 * @param string         $pathString Path
	 * @param callable|array $handler    Callback
	 *
	 * @return CliRoute
	 */
	public static function create(string $type, string $pathString, callable|array $handler) : CliRoute {
		$route = new self($type, $handler);
		$route->path = array_filter(explode('/', $pathString), 'not_empty');
		self::insertIntoAvailableRoutes($route->path, $type, $route);
		return $route;
	}

	/**
	 * Add a new route into availableRoutes array
	 *
	 * @param string[] $path  Route path
	 * @param string   $type  Route type (GET, POST, DELETE, PUT)
	 * @param CliRoute $route Route object
	 */
	protected static function insertIntoAvailableRoutes(array $path, string $type, CliRoute $route) : void {
		$routes = &self::$availableRoutes;
		foreach ($path as $name) {
			$name = strtolower($name);
			if (!isset($routes[$name])) {
				$routes[$name] = [];
			}
			$routes = &$routes[$name];
		}
		if (!isset($routes[$type])) {
			$routes[$type] = [];
		}
		$routes = &$routes[$type];
		$routes[] = $route;
	}

	/**
	 * Get set route if it exists
	 *
	 * @param string $type   [GET, POST, DELETE, PUT]
	 * @param array  $path   URL path as an array
	 * @param array  $params URL parameters in a key-value array
	 *
	 * @return Route|null
	 */
	public static function getRoute(string $type, array $path, array &$params = []) : ?CliRoute {
		$routes = self::$availableRoutes;
		foreach ($path as $value) {
			if (isset($routes[$value])) {
				$routes = $routes[$value];
				continue;
			}

			$paramRoutes = array_filter($routes, static function(string $key) {
				return preg_match('/({[^}]+})/', $key) > 0;
			},                          ARRAY_FILTER_USE_KEY);
			if (count($paramRoutes) === 1) {
				$name = substr(array_keys($paramRoutes)[0], 1, -1);
				$routes = reset($paramRoutes);
				$params[$name] = $value;
				continue;
			}

			return null;
		}
		if (isset($routes[$type]) && count($routes[$type]) !== 0) {
			return reset($routes[$type]);
		}
		return null;
	}

	/**
	 * Handle a Request - calls any set Middleware and calls a route callback
	 *
	 * @param Request|null $request
	 */
	public function handle(?RequestInterface $request = null) : void {
		if (is_array($this->handler)) {
			if (class_exists($this->handler[0])) {
				[$class, $func] = $this->handler;
				/** @var ControllerInterface $controller */
				$controller = new $class;

				$controller->init($request);
				$controller->$func($request);
			}
		}
		else {
			call_user_func($this->handler, $request);
		}
	}


	/**
	 * @return array|callable
	 */
	public function getHandler() : callable|array {
		return $this->handler;
	}

	/**
	 * @param string $usage
	 *
	 * @return CliRoute
	 */
	public function usage(string $usage) : CliRoute {
		$this->usage = $usage;
		return $this;
	}

	/**
	 * @param string $description
	 *
	 * @return CliRoute
	 */
	public function description(string $description) : CliRoute {
		$this->description = $description;
		return $this;
	}

	/**
	 * @param callable $help
	 *
	 * @return CliRoute
	 */
	public function help(callable $help) : CliRoute {
		$this->helpPrint = $help;
		return $this;
	}

	/**
	 * @param array{name:string,isOptional:bool,description:string,suggestions:array} ...$argument
	 *
	 * @return $this
	 */
	public function addArgument(array ...$argument) : CliRoute {
		foreach ($argument as $arg) {
			$this->arguments[] = $arg;
		}
		return $this;
	}

}
