<?php

namespace App\Controllers\Cli;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use JsonException;
use Lsr\Core\CliController;
use Lsr\Core\Requests\CliRequest;
use Lsr\Core\Routing\CliRoute;
use Lsr\Core\Routing\Router;
use Lsr\Enums\RequestMethod;
use Lsr\Helpers\Cli\CliHelper;
use Lsr\Interfaces\RouteInterface;

class Help extends CliController
{

	/**
	 * Generate and output an CLI commands JSON for autocomplete tools
	 *
	 * @param CliRequest $request
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function generateAutocompleteJson(CliRequest $request) : void {
		$outFile = $request->args[0] ?? '';
		$out = [
			'name'        => 'lac',
			'description' => 'Laser arena control CLI tools',
			'subcommands' => [],
		];
		// Add all routes (subcommands)
		$this->addRoutes(Router::$availableRoutes, $out);

		$json = json_encode($out, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		// Output JSON
		if (empty($outFile)) {
			echo $json.PHP_EOL;
		}
		else {
			file_put_contents($outFile, $json);
		}
	}

	/**
	 * @param array<RouteInterface[]|RouteInterface> $routes
	 * @param array{
	 *     subcommands: array{
	 *         name:string,
	 *         description:string,
	 *         args:array{
	 *             name: string, description?: string, isOptional?:bool, suggestions: string[]
	 *         }[]
	 *    }[]
	 * }                                             $out
	 *
	 * @return void
	 */
	public function addRoutes(array $routes, array &$out = ['subcommands' => []]) : void {
		foreach ($routes as $route) {
			if ($route instanceof CliRoute) {
				$out['subcommands'][] = [
					'name'        => implode('/', $route->path),
					'description' => $route->description,
					'args'        => $route->arguments,
				];
			}
			else if (is_array($route)) {
				$this->addRoutes($route, $out);
			}
		}
	}

	public function listCommands(CliRequest $request) : void {
		$group = $request->args[0] ?? '';
		/** @var string[] $routes */
		$routes = $this->formatRoutes(Router::$availableRoutes);
		$currGroup = '';
		if (!empty($group)) {
			$groups = explode('/', $group);
			foreach ($groups as $group) {
				$group = trailingSlashIt($group);
				$currGroup .= $group;
				if (!isset($routes[$group])) {
					CliHelper::printErrorMessage('Invalid command group "%s"', $currGroup);
					exit(1);
				}
				if (!is_string($routes[$group])) {
					$routes = $routes[$group];
				}
				else {
					$currGroup = substr($currGroup, 0, -strlen($group));
				}
			}
		}
		$this->printRoutes($routes, $currGroup);
	}

	/**
	 * Formats routing array to more readable format
	 *
	 * @param RouteInterface[]|RouteInterface[][] $routes
	 *
	 * @return array<string, string|array<string, string>>|string
	 */
	private function formatRoutes(array $routes) : array|string {
		$formatted = [];
		foreach ($routes as $key => $route) {
			if (!is_array($route)) {
				continue;
			}
			if (count($route) === 1) {
				$routeObj = first($route);
				if ($routeObj instanceof CliRoute) {
					return $routeObj->description;
				}
			}

			$formatted[$key.'/'] = $this->formatRoutes($route);
		}
		/** @var array<string, string|array<string, string>> $formatted */
		return $formatted;
	}

	/**
	 * @param string[]|string[][] $routes
	 * @param string              $currentKey
	 *
	 * @return void
	 */
	private function printRoutes(array $routes, string $currentKey = '') : void {
		foreach ($routes as $key => $route) {
			if (is_string($route)) {
				echo Colors::color(ForegroundColors::GREEN).$currentKey.substr($key, 0, -1).Colors::reset().PHP_EOL."\t\t".$route.PHP_EOL;
				continue;
			}
			$this->printRoutes($route, $currentKey.$key);
		}
	}

	/**
	 * Print help information about a specific command
	 *
	 * @param CliRequest $request
	 *
	 * @return void
	 */
	public function help(CliRequest $request) : void {
		$path = $request->args[0] ?? '';
		if (empty($path)) {
			CliHelper::printErrorMessage('Missing required argument (1)');
			$route = $request->getRoute();
			if (isset($route)) {
				CliHelper::printRouteUsage($route);
			}
			exit(1);
		}

		/** @var CliRoute|null $route */
		$route = Router::getRoute(RequestMethod::CLI, explode('/', $path));
		if (!isset($route)) {
			CliHelper::printErrorMessage('Cannot find command "%s"'.PHP_EOL.'Use "%s list" to list all available commands.', $path, CliHelper::getCaller());
			exit(1);
		}

		echo Colors::color(ForegroundColors::CYAN).lang('Description', context: 'cli.messages').':'.Colors::reset().PHP_EOL;
		echo $route->description.PHP_EOL;
		CliHelper::printRouteUsage($route);
		CliHelper::printRouteHelp($route);
	}

}