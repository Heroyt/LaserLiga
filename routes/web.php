<?php

use App\Controllers\Dashboard;
use App\Controllers\Games;
use App\Core\Routing\Route;

Route::get('/', [Dashboard::class, 'show'])->name('dashboard');

Route::get('/game', [Games::class, 'show'])->name('game-empty');    // This will result in HTTP 404 error
Route::get('/g', [Games::class, 'show'])->name('game-empty-alias'); // This will result in HTTP 404 error
Route::get('/game/{game}', [Games::class, 'show'])->name('game');
Route::get('/g/{game}', [Games::class, 'show'])->name('game-alias');
Route::get('players/leaderboard', [Games::class, 'todayLeaderboard']);
Route::get('players/leaderboard/{system}', [Games::class, 'todayLeaderboard']);
Route::get('players/leaderboard/{system}/{date}', [Games::class, 'todayLeaderboard']);
Route::get('players/leaderboard/{system}/{date}/{property}', [Games::class, 'todayLeaderboard'])->name('today-leaderboard');