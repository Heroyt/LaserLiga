<?php
declare(strict_types=1);

use App\Controllers\Google\GoogleAuthController;
use App\Core\Middleware\CanManageArena;
use App\Core\Middleware\LoggedIn;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Routing\Router;

/** @var Router $this */

$auth = App::getService('auth');
assert($auth instanceof Auth);

$google = $this->group('google')
               ->middlewareAll(
	               new LoggedIn($auth, ['manage-arena']),
	               new CanManageArena([['manage-arena']])
               );

$dropboxArena = $google->group('{arena}');
$dropboxArena->get('start', [GoogleAuthController::class, 'start']);
$dropboxArena->get('auth', [GoogleAuthController::class, 'auth']);