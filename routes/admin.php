<?php

use App\Controllers\Admin\Arenas;
use App\Controllers\Admin\Games;
use App\Core\Middleware\LoggedIn;
use Lsr\Core\Routing\Route;

$adminGroup = Route::group('/admin');

// Arenas
$arenasMiddleware = new LoggedIn(['manage-arenas']);

$adminGroup->group('/arenas')
					 ->middlewareAll($arenasMiddleware)
					 ->get('/', [Arenas::class, 'show'])->name('admin-arenas')
					 ->post('/', [Arenas::class, 'create'])
					 ->get('/{id}', [Arenas::class, 'edit'])->name('admin-arenas-edit')
					 ->post('/{id}', [Arenas::class, 'process'])
					 ->post('/{id}/image', [Arenas::class, 'imageUpload'])
					 ->post('/{id}/apikey', [Arenas::class, 'generateApiKey'])
					 ->post('/apikey/{id}/invalidate', [Arenas::class, 'invalidateApiKey'])
					 ->endGroup();

$gamesMiddleware = new LoggedIn(['manage-games']);
$adminGroup->group('/games')
					 ->get('/notification/{code}', [Games::class, 'sendGameNotification'])
					 ->get('/create', [Games::class, 'create'])->name('admin-create-game')
					 ->post('/create', [Games::class, 'createProcess']);