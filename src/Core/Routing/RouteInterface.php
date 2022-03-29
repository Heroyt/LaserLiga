<?php

namespace App\Core\Routing;

use App\Core\Interfaces\RequestInterface;

interface RouteInterface
{

	// Request types
	public const CLI             = 'CLI';
	public const GET             = 'GET';
	public const POST            = 'POST';
	public const DELETE          = 'DELETE';
	public const UPDATE          = 'PUT';
	public const REQUEST_METHODS = [self::GET, self::POST, self::DELETE, self::UPDATE, self::CLI];

	/**
	 * Route constructor.
	 *
	 * @param string         $type
	 * @param callable|array $handler
	 */
	public function __construct(string $type, callable|array $handler);

	/**
	 * Create a new route
	 *
	 * @param string         $type       [GET, POST, DELETE, PUT]
	 * @param string         $pathString Path
	 * @param callable|array $handler    Callback
	 *
	 * @return RouteInterface
	 */
	public static function create(string $type, string $pathString, callable|array $handler) : RouteInterface;

	/**
	 * Get set route if it exists
	 *
	 * @param string $type   [GET, POST, DELETE, PUT, CLI]
	 * @param array  $path   URL path as an array
	 * @param array  $params URL parameters in a key-value array
	 *
	 * @return RouteInterface|null
	 */
	public static function getRoute(string $type, array $path, array &$params = []) : ?RouteInterface;

	/**
	 * Handle a Request - calls any set Middleware and calls a route callback
	 *
	 * @param RequestInterface|null $request
	 */
	public function handle(?RequestInterface $request = null) : void;

}