<?php
declare(strict_types=1);

use App\Controllers\Kiosk\Dashboard;
use App\Controllers\Kiosk\Manifest;
use App\Core\Middleware\ContentLanguageHeader;
use App\Core\Middleware\StartKioskSession;
use Lsr\Core\Middleware\DefaultLanguageRedirect;
use Lsr\Core\Routing\Router;

/** @var Router $this */
$routes = $this->group('[lang=cs]')->middlewareAll(new DefaultLanguageRedirect(), new ContentLanguageHeader());
$routes->get('manifest_kiosk.json', [Manifest::class, 'getManifest']);
$routes->get('kiosk/exit', [Dashboard::class, 'exit']);

$kioskGroup = $routes->group('kiosk/{arenaId}')->middlewareAll(new StartKioskSession());

$kioskGroup->get('', [Dashboard::class, 'show'])->name('kiosk-dashboard');
$kioskGroup->get('{type}', [Dashboard::class, 'show'])->name('kiosk-dashboard-type');
