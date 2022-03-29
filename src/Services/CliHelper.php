<?php

namespace App\Services;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Cli\Enums\TextAttributes;
use App\Core\Routing\CliRoute;

/**
 * Helper function for CLI tools
 */
class CliHelper
{

	/**
	 * Print a formatted error message to the stderr
	 *
	 * @param string $message
	 * @param mixed  ...$args
	 *
	 * @return void
	 */
	public static function printErrorMessage(string $message, ...$args) : void {
		$message = Colors::color(foreground: ForegroundColors::RED, attribute: TextAttributes::BOLD).
			lang('Error', context: 'cli.messages').': '.
			Colors::color(attribute: TextAttributes::UN_BOLD).lang($message, context: 'cli.errors').
			Colors::reset().PHP_EOL;
		fprintf(STDERR, $message, ...$args);
	}

	/**
	 * @return void
	 */
	public static function printUsage() : void {
		echo PHP_EOL.Colors::color(ForegroundColors::GREEN, attribute: TextAttributes::BOLD).lang('Usage', context: 'cli.messages').':'.Colors::reset().PHP_EOL;
		echo TextAttributes::BOLD->value.self::getCaller().TextAttributes::UN_BOLD->value.' <request> [arguments...]'.PHP_EOL.PHP_EOL;
	}

	/**
	 * @return string
	 */
	public static function getCaller() : string {
		global $argv;
		$caller = $argv[0];
		if (str_contains($caller, 'php')) {
			$caller .= ' '.$argv[1];
		}
		return $caller;
	}

	/**
	 * @param CliRoute $route
	 *
	 * @return void
	 */
	public static function printRouteUsage(CliRoute $route) : void {
		echo PHP_EOL.Colors::color(ForegroundColors::GREEN, attribute: TextAttributes::BOLD).lang('Usage', context: 'cli.messages').':'.Colors::reset().PHP_EOL;
		echo TextAttributes::BOLD->value.self::getCaller().' '.implode('/', $route->path).TextAttributes::UN_BOLD->value.' '.$route->usage.PHP_EOL;
	}

	/**
	 * @param CliRoute $route
	 *
	 * @return void
	 */
	public static function printRouteHelp(CliRoute $route) : void {

		self::printRouteArguments($route);

		if (is_callable($route->helpPrint)) {
			echo PHP_EOL;
			call_user_func($route->helpPrint);
		}
	}

	/**
	 * @param CliRoute $route
	 *
	 * @return void
	 */
	public static function printRouteArguments(CliRoute $route) : void {
		if (empty($route->arguments)) {
			return;
		}
		echo PHP_EOL.Colors::color(ForegroundColors::YELLOW).lang('Arguments', context: 'cli.messages').':'.Colors::reset().PHP_EOL;
		foreach ($route->arguments as $argument) {
			$name = $argument['isOptional'] ?? false ? "[{$argument['name']}]" : "<{$argument['name']}>";
			echo Colors::color(ForegroundColors::BLUE).$name.Colors::reset().PHP_EOL."\t".lang($argument['description'] ?? '', context: 'cli.help.arguments').PHP_EOL;
		}
	}

	/**
	 * @param array  $routes
	 * @param array  $routesAll
	 * @param string $currKey
	 *
	 * @return array
	 */
	public static function getAllCommands(array &$routes = [], array $routesAll = [], string $currKey = '') : array {
		if (empty($routesAll)) {
			$routesAll = CliRoute::$availableRoutes;
		}
		foreach ($routesAll as $key => $route) {
			if (count($route) === 1 && ($route[0] ?? null) instanceof CliRoute) {
				$routes[] = $currKey;
			}
			else {
				self::getAllCommands($routes, $route, empty($currKey) ? $key : $currKey.'/'.$key);
			}
		}
		return $routes;
	}

}