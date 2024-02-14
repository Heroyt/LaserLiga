<?php

use App\Controllers\Admin\Arenas;
use App\Controllers\Admin\Games;
use App\Controllers\Admin\TournamentStats;
use App\Core\Middleware\LoggedIn;
use Lsr\Core\Routing\Route;

$adminGroup = Route::group('/admin');

// Arenas
$arenasMiddleware = new LoggedIn(['manage-arenas']);
$gamesMiddleware = new LoggedIn(['manage-games']);
$tournamentMiddleware = new LoggedIn(['manage-tournaments']);

$arenasAdminGroup = $adminGroup->group('/arenas')
                               ->middlewareAll($arenasMiddleware);

$arenasAdminGroup->get('', [Arenas::class, 'show'])->name('admin-arenas')
                 ->post('', [Arenas::class, 'create'])
                 ->post('/apikey/{id}/invalidate', [Arenas::class, 'invalidateApiKey']);

$arenasAdminGroup->group('{id}')
                 ->get('', [Arenas::class, 'edit'])->name('admin-arenas-edit')
                 ->post('', [Arenas::class, 'process'])
                 ->post('image', [Arenas::class, 'imageUpload'])
                 ->post('apikey', [Arenas::class, 'generateApiKey']);

$adminGroup->group('games')
           ->get('notification/{code}', [Games::class, 'sendGameNotification'])
           ->get('create', [Games::class, 'create'])->name('admin-create-game')
           ->post('create', [Games::class, 'createProcess']);

$adminGroup->group('tournament')
	->middlewareAll($tournamentMiddleware)
           ->get('stats/tournament/{id}', [TournamentStats::class, 'tournamentStats'])
           ->get('stats/league/{id}', [TournamentStats::class, 'leagueStats'])
           ->get('stats/league/{leagueId}/{categoryId}', [TournamentStats::class, 'leagueStats']);