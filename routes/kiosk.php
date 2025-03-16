<?php
declare(strict_types=1);

use App\Controllers\Kiosk\Dashboard;
use App\Controllers\Kiosk\Manifest;
use App\Core\Middleware\StartKioskSession;
use Lsr\Core\Routing\Router;

/** @var Router $this */

$this->get('manifest_kiosk.json', [Manifest::class, 'getManifest']);
$this->get('kiosk/exit', [Dashboard::class, 'exit']);

$kioskGroup = $this->group('kiosk/{arenaId}')->middlewareAll(new StartKioskSession());

$kioskGroup->get('', [Dashboard::class, 'show'])->name('kiosk-dashboard');
$kioskGroup->get('{type}', [Dashboard::class, 'show'])->name('kiosk-dashboard-type');
