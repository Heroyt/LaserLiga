<?php

use App\Controllers\Dashboard;
use App\Controllers\User\LeaderboardController;
use App\Controllers\User\StatController;
use App\Controllers\User\UserController;
use App\Controllers\User\UserGameController;
use App\Controllers\User\UserTournamentController;
use App\Core\Middleware\CSRFCheck;
use App\Core\Middleware\LoggedIn;
use Lsr\Core\Routing\Route;

$loggedIn = new LoggedIn();

$routes = Route::group()
               ->middlewareAll($loggedIn)
               ->get('/dashboard', [Dashboard::class, 'show'])->name('dashboard');

$publicUserRoutes = Route::group('/user')
                         ->get('/leaderboard', [LeaderboardController::class, 'show'])->name('player-leaderboard')
                         ->get('/leaderboard/{arenaId}', [LeaderboardController::class, 'show'])->name(
		'player-leaderboard-arena'
	);

$publicUserIdGroup = $publicUserRoutes
	->group('/{code}')
	->get('/', [UserController::class, 'public'])->name('public-profile')
	->get('/img', [UserController::class, 'thumb'])
	->get('/avatar', [UserController::class, 'avatar'])
	->post('/avatar', [UserController::class, 'updateAvatar'])
	->get('/title/svg', [UserController::class, 'title'])
	->get('/history', [UserController::class, 'gameHistory'])->name('player-game-history')
	->get('/tournaments', [UserTournamentController::class, 'myTournaments'])->name('player-tournaments')
	->group('stats')
	->get('trends', [UserController::class, 'getTrends'])
	->get('rankhistory', [StatController::class, 'rankHistory'])
	->get('rankorderhistory', [StatController::class, 'rankOrderHistory'])
	->get('gamecounts', [StatController::class, 'games'])
	->get('modes', [StatController::class, 'modes'])
	->get('radar', [StatController::class, 'radar'])
	->get('trophies', [StatController::class, 'trophies'])
	->get('achievements', [StatController::class, 'achievements'])
	->group('rank')
	->get('history', [StatController::class, 'rankHistory'])
	->get('orderhistory', [StatController::class, 'rankOrderHistory'])
	->endGroup();

$userGroup = $routes
	->group('/user')
	->get('/', [UserController::class, 'show'])->name('profile')
	->post('/', [UserController::class, 'processProfile'])->middleware(new CSRFCheck('user-profile'))
	->get('/history', [UserController::class, 'gameHistory'])->name('my-game-history')
	->get('/tournaments', [UserTournamentController::class, 'myTournaments'])->name('my-tournaments')
	->get('/findgames', [UserController::class, 'findGames'])->name('find-my-games')
	->group('/player')
	->post('/setme', [UserGameController::class, 'setMe'])
	->post('/setallme', [UserGameController::class, 'setAllMe'])
	->post('/setnotme', [UserGameController::class, 'setNotMe'])
	->post('/unsetme', [UserGameController::class, 'unsetMe'])
	->post('/setmegroup', [UserGameController::class, 'setGroupMe'])
	->post('/{id}/stats', [UserGameController::class, 'updateStats'])
	->endGroup()
	->get('/{code}/compare', [UserController::class, 'getUserCompare']);
