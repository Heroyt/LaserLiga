<?php


namespace App\Core\Routing;


use App\Core\Interfaces\ControllerInterface;
use App\Core\Interfaces\RequestInterface;
use App\Core\Request;
use InvalidArgumentException;

class Route implements RouteInterface
{


	/** @var Route[] Structure holding all set routes */
	public static array $availableRoutes = [];
	/** @var array<string, Route> Array of named routes with their names as array keys */
	public static array $namedRoutes = [];
	/** @var string[] Current URL path as an array (exploded using the "/") */
	public array $path = [];

	/** @var callable|array $handler Route callback */
	protected $handler;
	/** @var Middleware[] Route's middleware objects */
	protected array  $middleware = [];
	protected string $routeName  = '';


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
	 * @return Route
	 */
	public static function get(string $pathString, callable|array $handler) : Route {
		return self::create(self::GET, $pathString, $handler);
	}

	/**
	 * Create a new route
	 *
	 * @param string         $type       [GET, POST, DELETE, PUT]
	 * @param string         $pathString Path
	 * @param callable|array $handler    Callback
	 *
	 * @return Route
	 */
	public static function create(string $type, string $pathString, callable|array $handler) : Route {
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
	 * @param Route    $route Route object
	 */
	protected static function insertIntoAvailableRoutes(array $path, string $type, Route $route) : void {
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
	 * Create a new POST route
	 *
	 * @param string         $pathString
	 * @param callable|array $handler
	 *
	 * @return Route
	 */
	public static function post(string $pathString, callable|array $handler) : Route {
		return self::create(self::POST, $pathString, $handler);
	}

	/**
	 * Create a new UPDATE route
	 *
	 * @param string         $pathString
	 * @param callable|array $handler
	 *
	 * @return Route
	 */
	public static function update(string $pathString, callable|array $handler) : Route {
		return self::create(self::UPDATE, $pathString, $handler);
	}

	/**
	 * Create a new DELETE route
	 *
	 * @param string         $pathString
	 * @param callable|array $handler
	 *
	 * @return Route
	 */
	public static function delete(string $pathString, callable|array $handler) : Route {
		return self::create(self::DELETE, $pathString, $handler);
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
	public static function getRoute(string $type, array $path, array &$params = []) : ?Route {
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
	 * Get named Route object if it exists
	 *
	 * @param string $name
	 *
	 * @return Route|null
	 */
	public static function getRouteByName(string $name) : ?Route {
		return self::$namedRoutes[$name] ?? null;
	}

	/**
	 * Handle a Request - calls any set Middleware and calls a route callback
	 *
	 * @param Request|null $request
	 */
	public function handle(?RequestInterface $request = null) : void {
		if (!isset($request)) {
			throw new InvalidArgumentException('Request cannot be null.');
		}

		// Route-wide middleware
		foreach ($this->middleware as $middleware) {
			$middleware->handle($request);
		}

		if (is_array($this->handler)) {
			if (class_exists($this->handler[0])) {
				[$class, $func] = $this->handler;
				/** @var ControllerInterface $controller */
				$page = new $class;

				// Class-wide middleware
				foreach ($page->middleware as $middleware) {
					$middleware->handle($request);
				}

				$page->init($request);
				$page->$func($request);
			}
		}
		else {
			call_user_func($this->handler, $request);
		}
	}

	/**
	 * Adds a middleware object to the Route
	 *
	 * @param Middleware[] $middleware
	 */
	public function middleware(Middleware ...$middleware) : Route {
		$this->middleware = array_merge($this->middleware, $middleware);
		return $this;
	}

	/**
	 * Names a route
	 *
	 * @param string $name
	 *
	 * @return $this
	 */
	public function name(string $name) : Route {
		if (isset(self::$namedRoutes[$name]) && self::$namedRoutes[$name] !== $this) {
			throw new InvalidArgumentException('Route of this name already exists. ('.$name.')');
		}
		$this->routeName = $name;
		self::$namedRoutes[$name] = $this;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getRouteName() : string {
		return $this->routeName;
	}

	/**
	 * @return array|callable
	 */
	public function getHandler() : callable|array {
		return $this->handler;
	}

}
