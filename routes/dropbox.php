<?php
declare(strict_types=1);

use App\Controllers\Dropbox\DropboxAuthController;
use App\Core\Middleware\LoggedIn;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Routing\Router;

/** @var Router $this */

$auth = App::getService('auth');
assert($auth instanceof Auth);

$loggedIn = new LoggedIn($auth);

$dropbox = $this->group('dropbox')->middlewareAll($loggedIn);

$dropboxArena = $dropbox->group('{id}');
$dropboxArena->get('start', [DropboxAuthController::class, 'redirectToAuth']);
$dropboxArena->get('auth', [DropboxAuthController::class, 'auth']);