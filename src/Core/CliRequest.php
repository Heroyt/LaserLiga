<?php


namespace App\Core;


use App\Core\Interfaces\RequestInterface;
use App\Core\Routing\CliRoute;
use App\Core\Routing\RouteInterface;
use App\Services\CliHelper;
use Nette\Utils\Helpers;

class CliRequest implements RequestInterface
{

	// TODO: Parse additional cli args and opts

	public string       $type    = RouteInterface::CLI;
	public array        $path    = [];
	public array        $args    = [];
	public array        $params  = [];
	public array        $errors  = [];
	public array        $notices = [];
	protected ?CliRoute $route   = null;

	public function __construct(array|string $query) {
		global $argv;
		if (is_array($query)) {
			$this->parseArrayQuery($query);
		}
		else {
			$this->parseStringQuery($query);
		}

		if (empty($this->path)) {
			CliHelper::printErrorMessage('Missing the required path argument (1)');
			CliHelper::printUsage();
			exit(1);
		}

		$this->route = CliRoute::getRoute(RouteInterface::CLI, $this->path, $this->params);
		$this->args = array_slice($argv, 2);
	}

	protected function parseArrayQuery(array $query) : void {
		$this->path = array_map('strtolower', $query);
	}

	protected function parseStringQuery(string $query) : void {
		$this->parseArrayQuery(array_filter(explode('/', $query), static function($a) {
			return !empty($a);
		}));
	}

	public function handle() : void {
		if (isset($this->route)) {
			$this->route->handle($this);
		}
		else {
			$request = implode('/', $this->path);
			$suggestion = Helpers::getSuggestion(CliHelper::getAllCommands(), $request);
			CliHelper::printErrorMessage('Unknown request "%s". %s', $request, isset($suggestion) ? 'Did you mean: "'.$suggestion.'"?' : '');
			fprintf(STDERR, PHP_EOL.'To list all available commands use:'.PHP_EOL.CliHelper::getCaller().' list'.PHP_EOL);
			exit(1);
		}
	}

	public function __get($name) {
		return $this->params[$name] ?? null;
	}

	public function __set($name, $value) {
		$this->params[$name] = $value;
	}

	public function __isset($name) {
		return isset($this->params[$name]);
	}

	/**
	 * @return CliRoute|null
	 */
	public function getRoute() : ?CliRoute {
		return $this->route;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() : array {
		return get_object_vars($this);
	}

}
