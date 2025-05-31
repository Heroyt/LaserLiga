<?php
/**
 * @file      index.php
 * @brief     Main php file accessed by user
 * @details   All user connections are directed here
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */


/** Root directory */

use App\Controllers\E400;
use App\Controllers\E404;
use App\CQRS\AsyncDispatcher;
use App\Exceptions\DispatchBreakException;
use App\Services\FontAwesomeManager;
use Lsr\Core\App;
use Lsr\Core\Requests\Exceptions\RouteNotFoundException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Exceptions\MethodNotAllowedException;
use Lsr\Helpers\Tools\Timer;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Tracy\Debugger;

const ROOT = __DIR__ . '/';
/** Visiting site normally */
const INDEX = true;

// For CLI use - init some important functions
if (PHP_SAPI === 'cli') {
	// Async signals is necessary for interrupt handling
	pcntl_async_signals(true);
	/** @var string $_ command used to run the script */
	$_ = $_SERVER['_'] ?? '/usr/local/bin/php';
	if ($_ === '/bin/sh') {
		$_ = '/usr/local/bin/php';
	}
}

session_cache_limiter('');
require_once ROOT . "include/load.php";

$app = App::getInstance();

Timer::start('app');

try {
	$app->getRequest(); // Test request parse
	try {
		$response = $app->run();
	} catch (RouteNotFoundException|ModelNotFoundException|\Lsr\Core\Routing\Exceptions\ModelNotFoundException $e) {
		bdump($e);
		// Handle 404 Error
		$controller = App::getContainer()->getByType(E404::class);
		/** @var Request $request */
		$request = $app->getRequest();
		$controller->init($request);
		$response = $controller->show($request, $e);
	} catch (DispatchBreakException $e) {
		$response = $e->getResponse();
	} catch (MethodNotAllowedException $e) {
		$response = new \Lsr\Core\Requests\Response(new Response(405, ['Content-Type' => 'text/plain'], $e->getMessage()));
	} catch (\Lsr\Core\Routing\Exceptions\AccessDeniedException $e) {
		$response = \Lsr\Core\Requests\Response::create(403, [], $e->getMessage());
	}
} catch (JsonException $e) {
	$request = new Request(new ServerRequest($_SERVER['REQUEST_METHOD'], $_SERVER['SCRIPT_URI']));
	$controller = App::getContainer()->getByType(E400::class);
	$controller->init($request);
	$response = $controller->show($request, $e);
}
Timer::stop('app');

sendResponse($response);
Debugger::shutdownHandler();
$app->session->close();
fastcgi_finish_request();

// Finish other tasks after response is sent (almost async)

$fontawesome = $app::getService('fontawesome');
assert($fontawesome instanceof FontAwesomeManager, 'Invalid fontawesome manager instance from DI');
$app->translations->updateTranslations();
$fontawesome->saveIcons();

// Handle async commands
$dispatcher = $app::getService('cqrs.asyncDispatcher');
assert($dispatcher instanceof AsyncDispatcher, 'Invalid async dispatcher instance from DI');
$dispatcher->dispatchAsyncQueue();

function sendResponse(ResponseInterface $response): void {
	// Check if something is not already sent
	if (headers_sent()) {
		throw new RuntimeException('Headers were already sent. The response could not be emitted!');
	}

	// Status code
	http_response_code($response->getStatusCode());

	// Send headers
	foreach ($response->getHeaders() as $name => $values) {
		foreach ($values as $value) {
			header(sprintf('%s: %s', $name, $value), false);
		}
	}

	// Send body
	$stream = $response->getBody();

	if (!$stream->isReadable()) {
		return;
	}

	if ($stream->isSeekable()) {
		$stream->rewind();
	}

	while (!$stream->eof()) {
		echo $stream->read(8192);
	}
}