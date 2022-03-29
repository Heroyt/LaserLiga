<?php

use App\Controllers\Dashboard;
use App\Core\Routing\Route;

Route::get('/', [Dashboard::class, 'show'])->name('dashboard');
