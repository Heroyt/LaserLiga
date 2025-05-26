<?php
declare(strict_types=1);

use App\Controllers\Dropbox\DropboxAuthController;
use App\Core\Middleware\CanManageArena;
use App\Core\Middleware\LoggedIn;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Routing\Router;

/** @var Router $this */

$auth = App::getService('auth');
assert($auth instanceof Auth);

$dropbox = $this->group('dropbox')
                ->middlewareAll(
	                new LoggedIn($auth, ['manage-arena']),
	                new CanManageArena([['manage-arena']])
                );

$dropboxArena = $dropbox->group('{id}');
$dropboxArena->get('start', [DropboxAuthController::class, 'redirectToAuth']);
$dropboxArena->get('auth', [DropboxAuthController::class, 'auth']);