<?php
declare(strict_types=1);

use App\Controllers\Kiosk\Dashboard;
use App\Controllers\Kiosk\Manifest;
use App\Core\Middleware\StartKioskSession;
use Lsr\Core\Routing\Route;

Route::get('manifest_kiosk.json', [Manifest::class, 'getManifest']);
Route::get('kiosk/exit', [Dashboard::class, 'exit']);

$kioskGroup = Route::group('kiosk/{arenaId}')->middlewareAll(new StartKioskSession());

$kioskGroup->get('', [Dashboard::class, 'show'])->name('kiosk-dashboard');
$kioskGroup->get('{type}', [Dashboard::class, 'show'])->name('kiosk-dashboard-type');
