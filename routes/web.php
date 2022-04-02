<?php

use App\Controllers\Dashboard;
use App\Controllers\Games;
use App\Core\Routing\Route;

Route::get('/', [Dashboard::class, 'show'])->name('dashboard');

Route::get('/game', [Games::class, 'show'])->name('game-empty'); // This will result in HTTP 404 error
Route::get('/game/{game}', [Games::class, 'show'])->name('game');