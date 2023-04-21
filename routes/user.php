<?php

use App\Controllers\Dashboard;
use App\Controllers\User\LeaderboardController;
use App\Controllers\User\StatController;
use App\Controllers\User\UserController;
use App\Controllers\User\UserGameController;
use App\Core\Middleware\CSRFCheck;
use App\Core\Middleware\LoggedIn;
use Lsr\Core\Routing\Route;

$loggedIn = new LoggedIn();

$routes = Route::group()
							 ->middlewareAll($loggedIn)
							 ->get('/dashboard', [Dashboard::class, 'show'])->name('dashboard');

$publicUserRoutes = Route::group('/user')
												 ->get('/leaderboard', [LeaderboardController::class, 'show'])->name('player-leaderboard')
												 ->get('/leaderboard/{arenaId}', [LeaderboardController::class, 'show'])->name('player-leaderboard-arena');

$publicUserIdGroup = $publicUserRoutes
	->group('/{code}')
	->get('/', [UserController::class, 'public'])->name('public-profile')
	->get('/history', [UserController::class, 'gameHistory'])->name('player-game-history')
	->group('stats')
	->get('trends', [UserController::class, 'getTrends'])
	->get('rankhistory', [StatController::class, 'rankHistory'])
	->get('gamecounts', [StatController::class, 'games'])
	->get('modes', [StatController::class, 'modes'])
	->get('radar', [StatController::class, 'radar'])
	->get('trophies', [StatController::class, 'trophies']);

$userGroup = $routes
	->group('/user')
	->get('/', [UserController::class, 'show'])->name('profile')
	->post('/', [UserController::class, 'processProfile'])->middleware(new CSRFCheck('user-profile'))
	->get('/history', [UserController::class, 'gameHistory'])->name('my-game-history')
	->get('/findgames', [UserController::class, 'findGames'])->name('find-my-games')
	->group('/player')
	->post('/setme', [UserGameController::class, 'setMe'])
	->post('/setnotme', [UserGameController::class, 'setNotMe'])
	->post('/setmegroup', [UserGameController::class, 'setGroupMe'])
	->post('/{id}/stats', [UserGameController::class, 'updateStats'])
	->endGroup()
	->get('/{code}/compare', [UserController::class, 'getUserCompare']);
