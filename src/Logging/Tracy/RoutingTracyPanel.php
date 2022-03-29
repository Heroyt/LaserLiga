<?php

namespace App\Logging\Tracy;

use App\Core\App;
use App\Core\Routing\Route;
use Tracy\IBarPanel;

class RoutingTracyPanel implements IBarPanel
{

	/**
	 * @inheritDoc
	 */
	public function getTab() : string {
		return view('debug/Routing/tab', [], true);
	}

	/**
	 * @inheritDoc
	 */
	public function getPanel() : string {
		$routes = $this->formatRoutes(['' => Route::$availableRoutes]);
		return view('debug/Routing/panel', [
			'request' => App::getRequest()->request,
			'params'  => App::getRequest()->params,
			'path'    => App::getRequest()->path,
			'route'   => App::getRequest()?->getRoute(),
			'routes'  => $routes,
		],          true);
	}

	/**
	 * Formats routing array to more readable format
	 *
	 * @param array $routes
	 *
	 * @return array
	 */
	private function formatRoutes(array $routes) : array {
		$formatted = [];
		foreach ($routes as $key => $route) {
			if (count($route) === 1 && ($route[0] ?? null) instanceof Route) {
				$name = $route[0]->getRouteName();
				$formatted[$key] = (!empty($name) ? $name.': ' : '').$this->formatHandler($route[0]->getHandler());
			}
			else {
				$formatted[$key.'/'] = $this->formatRoutes($route);
			}
		}
		return $formatted;
	}

	/**
	 * Formats any type of handler to a string
	 *
	 * @param callable|array $handler
	 *
	 * @return string
	 */
	private function formatHandler(callable|array $handler) : string {
		if (is_string($handler)) {
			return $handler.'()';
		}
		if (is_array($handler)) {
			$class = array_shift($handler);
			return $class.'::'.implode('()->', $handler).'()';
		}
		return 'closure';
	}
}