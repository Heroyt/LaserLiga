<?php


namespace App\Core;


use App\Controllers\E404;
use App\Core\Interfaces\RequestInterface;
use App\Core\Routing\Route;
use App\Core\Routing\RouteInterface;

class Request implements RequestInterface
{

	public string    $type            = RouteInterface::GET;
	public array     $path            = [];
	public array     $query           = [];
	public array     $params          = [];
	public string    $body            = '';
	public array     $put             = [];
	public array     $post            = [];
	public array     $get             = [];
	public array     $request         = [];
	public array     $errors          = [];
	public array     $notices         = [];
	public array     $passErrors      = [];
	public array     $passNotices     = [];
	public ?Request  $previousRequest = null;
	protected ?Route $route           = null;

	public function __construct(array|string $query) {
		if (in_array($_SERVER['REQUEST_METHOD'], RouteInterface::REQUEST_METHODS, true)) {
			$this->type = $_SERVER['REQUEST_METHOD'];
		}
		if (is_array($query)) {
			$this->parseArrayQuery($query);
		}
		else {
			$this->parseStringQuery($query);
		}
		$this->query = array_filter($_GET, static function($key) {
			return $key !== 'p';
		},                          ARRAY_FILTER_USE_KEY);
		$this->route = Route::getRoute($this->type, $this->path, $this->params);
		if (isset($_SESSION['fromRequest'])) {
			$this->previousRequest = unserialize($_SESSION['fromRequest'], [__CLASS__]);
			unset($_SESSION['fromRequest']);
			$this->errors = array_merge($this->previousRequest->passErrors, $this->errors);
			$this->notices = array_merge($this->previousRequest->passNotices, $this->notices);
		}
		if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
			$input = fopen("php://input", 'rb');
			$this->body = '';
			while ($data = fread($input, 1024)) {
				$this->body .= $data;
			}
			fclose($input);
			if ($this->type === RouteInterface::POST) {
				$_POST = array_merge($_POST, json_decode($this->body, true, 512, JSON_THROW_ON_ERROR));
				$_REQUEST = array_merge($_REQUEST, $_POST);
			}
			elseif ($this->type === RouteInterface::UPDATE) {
				$this->put = array_merge($this->put, json_decode($this->body, true, 512, JSON_THROW_ON_ERROR));
				$_REQUEST = array_merge($_REQUEST, $this->put);
			}
			elseif ($this->type === RouteInterface::GET) {
				$_GET = array_merge($_GET, json_decode($this->body, true, 512, JSON_THROW_ON_ERROR));
				$_REQUEST = array_merge($_REQUEST, $_GET);
			}
		}
		$this->post = $_POST;
		$this->get = $_GET;
		$this->request = $_REQUEST;
	}

	protected function parseArrayQuery(array $query) : void {
		$this->path = array_map('strtolower', $query);
	}

	protected function parseStringQuery(string $query) : void {
		$url = parse_url($query);
		$filePath = urldecode(ROOT.substr($url['path'], 1));
		if (file_exists($filePath) && is_file($filePath)) {
			$extension = pathinfo($filePath, PATHINFO_EXTENSION);
			if ($extension !== 'php') {
				switch ($extension) {
					case 'css':
						$mime = 'text/css';
						break;
					case 'scss':
						$mime = 'text/x-scss';
						break;
					case 'sass':
						$mime = 'text/x-sass';
						break;
					case 'csv':
						$mime = 'text/csv';
						break;
					case 'css.map':
					case 'js.map':
					case 'map':
					case 'json':
						$mime = 'application/json';
						break;
					case 'js':
						$mime = 'text/javascript';
						break;
					default:
						$mime = mime_content_type($filePath);
						break;
				}
				header('Content-Type: '.$mime);
				exit(file_get_contents($filePath));
			}
		}
		$this->parseArrayQuery(array_filter(explode('/', $url['path']), static function($a) {
			return !empty($a);
		}));
	}

	public function handle() : void {
		if (isset($this->route)) {
			$this->route->handle($this);
		}
		else {
			$page = new E404();
			$page->init($this);
			$page->show();
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
	 * Check if current page is requested using AJAX call
	 *
	 * @return bool
	 */
	public function isAjax() : bool {
		return
			(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
			(!empty($_SERVER['X_REQUESTED_WITH']) && strtolower($_SERVER['X_REQUESTED_WITH']) === 'xmlhttprequest');
	}

	/**
	 * @return Route|null
	 */
	public function getRoute() : ?Route {
		return $this->route;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() : array {
		$vars = get_object_vars($this);
		if (isset($this->route) && !empty($this->route->getRouteName())) {
			$vars['routeName'] = $this->route->getRouteName();
		}
		return $vars;
	}

	/**
	 * Get IP of the client requesting
	 *
	 * @return string
	 */
	public function getIp() : string {
		return $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
	}
}
