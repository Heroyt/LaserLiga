<?php

use App\Controllers\Admin\Arenas;
use App\Core\Middleware\LoggedIn;
use Lsr\Core\Routing\Route;

// Arenas
$arenasMiddleware = new LoggedIn(['manage-arenas']);
Route::group('/admin')
		 ->group('/arenas')
		 ->middlewareAll($arenasMiddleware)
		 ->get('/', [Arenas::class, 'show'])->name('admin-arenas')
		 ->post('/', [Arenas::class, 'create'])
		 ->get('/{id}', [Arenas::class, 'edit'])->name('admin-arenas-edit')
		 ->post('/{id}', [Arenas::class, 'process'])
		 ->post('/{id}/image', [Arenas::class, 'imageUpload'])
		 ->post('/{id}/apikey', [Arenas::class, 'generateApiKey'])
		 ->post('/apikey/{id}/invalidate', [Arenas::class, 'invalidateApiKey'])
		 ->endGroup();