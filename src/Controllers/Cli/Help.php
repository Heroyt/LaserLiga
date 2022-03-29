<?php

namespace App\Controllers\Cli;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Core\CliController;
use App\Core\CliRequest;
use App\Core\Routing\CliRoute;
use App\Core\Routing\RouteInterface;
use App\Services\CliHelper;

class Help extends CliController
{

	/**
	 * Generate and output an CLI commands JSON for autocomplete tools
	 *
	 * @param CliRequest $request
	 *
	 * @return void
	 */
	public function generateAutocompleteJson(CliRequest $request) : void {
		$outFile = $request->args[0] ?? '';
		$out = [
			'name'        => 'hft',
			'description' => 'Heroyt\'s framework CLI tools',
			'subcommands' => [],
		];
		// Add all routes (subcommands)
		$this->addRoutes(CliRoute::$availableRoutes, $out);

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
	 * @param array<array|CliRoute> $routes
	 * @param array                 $out
	 *
	 * @return void
	 */
	public function addRoutes(array $routes, array &$out = []) : void {
		foreach ($routes as $route) {
			if ($route instanceof CliRoute) {
				$out['subcommands'][] = [
					'name'        => implode('/', $route->path),
					'description' => $route->description,
					'args'        => $route->arguments,
				];
			}
			else {
				$this->addRoutes($route, $out);
			}
		}
	}

	public function listCommands(CliRequest $request) : void {
		$group = $request->args[0] ?? '';
		$routes = $this->formatRoutes(CliRoute::$availableRoutes);
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
	 * @param array $routes
	 *
	 * @return array|string
	 */
	private function formatRoutes(array $routes) : array|string {
		$formatted = [];
		foreach ($routes as $key => $route) {
			if (count($route) === 1 && ($route[0] ?? null) instanceof CliRoute) {
				return $route[0]->description;
			}

			$formatted[$key.'/'] = $this->formatRoutes($route);
		}
		return $formatted;
	}

	/**
	 * @param array[] $routes
	 * @param string  $currentKey
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
			CliHelper::printRouteUsage($request->getRoute());
			exit(1);
		}

		$route = CliRoute::getRoute(RouteInterface::CLI, explode('/', $path));
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