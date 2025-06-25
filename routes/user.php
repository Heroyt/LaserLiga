<?php

use App\Controllers\Dashboard;
use App\Controllers\User\LeaderboardController;
use App\Controllers\User\StatController;
use App\Controllers\User\UserController;
use App\Controllers\User\UserFindGamesController;
use App\Controllers\User\UserGameController;
use App\Controllers\User\UserHistoryController;
use App\Controllers\User\UserPrivacyController;
use App\Controllers\User\UserSettingsController;
use App\Controllers\User\UserTournamentController;
use App\Core\Middleware\ContentLanguageHeader;
use App\Core\Middleware\CSRFCheck;
use App\Core\Middleware\LoggedIn;
use App\Core\Middleware\NoCacheControl;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Middleware\DefaultLanguageRedirect;
use Lsr\Core\Middleware\WithoutCookies;
use Lsr\Core\Routing\Router;

$auth = App::getService('auth');
assert($auth instanceof Auth);

/** @var Router $this */

$loggedIn = new LoggedIn($auth);
$withoutCookies = new WithoutCookies();
$noCacheControl = new NoCacheControl();

$langGroup = $this->group('[lang=cs]')->middlewareAll(new DefaultLanguageRedirect(), new ContentLanguageHeader());

$routes = $langGroup->group('')
                    ->middlewareAll($loggedIn, $noCacheControl)
                    ->get('/dashboard', [Dashboard::class, 'show'])->name('dashboard');

$privacyGroup = $routes->group('user/privacy')
                       ->get('agree', [UserPrivacyController::class, 'agree']);

$publicUserRoutes = $langGroup->group('/user')
                              ->get('/leaderboard', [LeaderboardController::class, 'show'])->name('player-leaderboard')
                              ->get('/leaderboard/{arenaId}', [LeaderboardController::class, 'show'])->name(
		'player-leaderboard-arena'
	);

$publicUserIdGroup = $publicUserRoutes
	->group('/{code}')
	->get('/', [UserController::class, 'show'])->name('public-profile')
	->get('/img', [UserController::class, 'thumb'])->middleware($withoutCookies)
	->get('/img.png', [UserController::class, 'thumb'])->middleware($withoutCookies)
	->get('/avatar', [UserController::class, 'avatar'])->middleware($withoutCookies)
	->post('/avatar', [UserSettingsController::class, 'updateAvatar'])
	->get('/title/svg', [UserController::class, 'title'])->middleware($withoutCookies)
	->get('/history', [UserHistoryController::class, 'show'])->name('player-game-history')
	->get('/tournaments', [UserTournamentController::class, 'myTournaments'])->name('player-tournaments')
	->group('stats')
	->middlewareAll($withoutCookies)
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
	->get('/', [UserSettingsController::class, 'show'])->name('profile')
	->post('/', [UserSettingsController::class, 'process'])->middleware(new CSRFCheck('user-profile'))
	->get('/history', [UserHistoryController::class, 'show'])->name('my-game-history')
	->get('/tournaments', [UserTournamentController::class, 'myTournaments'])->name('my-tournaments')
	->get('/findgames', [UserFindGamesController::class, 'show'])->name('find-my-games')
	->post('sendconfirm', [UserSettingsController::class, 'sendNewConfirmEmail'])
	->group('/player')
	->post('/setme', [UserGameController::class, 'setMe'])
	->post('/setallme', [UserGameController::class, 'setAllMe'])
	->post('/setnotme', [UserGameController::class, 'setNotMe'])
	->post('/unsetme', [UserGameController::class, 'unsetMe'])
	->post('/setmegroup', [UserGameController::class, 'setGroupMe'])
	->post('/{id}/stats', [UserGameController::class, 'updateStats'])
	->endGroup()
	->get('/{code}/compare', [UserController::class, 'getUserCompare']);
