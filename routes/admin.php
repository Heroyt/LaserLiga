<?php

use App\Controllers\Admin\Arenas;
use App\Controllers\Admin\Debug;
use App\Controllers\Admin\Games;
use App\Controllers\Admin\TournamentStats;
use App\Core\Middleware\LoggedIn;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Routing\Router;

/** @var Router $this */

$adminGroup = $this->group('/admin');

$auth = App::getService('auth');
assert($auth instanceof Auth);

// Arenas
$arenasMiddleware = new LoggedIn($auth, ['manage-arenas']);
$gamesMiddleware = new LoggedIn($auth, ['manage-games']);
$tournamentMiddleware = new LoggedIn($auth, ['manage-tournaments']);

$adminGroup->group('tracy')
           ->middlewareAll(new LoggedIn($auth, ['debug']))
           ->get('on', [Debug::class, 'turnOnTracy'])
           ->get('off', [Debug::class, 'turnOffTracy']);

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
           ->middlewareAll($gamesMiddleware)
           ->get('notification/{code}', [Games::class, 'sendGameNotification'])
           ->get('create', [Games::class, 'create'])->name('admin-create-game')
           ->post('create', [Games::class, 'createProcess']);

$adminGroup->group('tournament')
           ->middlewareAll($tournamentMiddleware)
           ->get('stats/tournament/{id}', [TournamentStats::class, 'tournamentStats'])
           ->get('stats/league/{id}', [TournamentStats::class, 'leagueStats'])
           ->get('stats/league/{leagueId}/{categoryId}', [TournamentStats::class, 'leagueStats']);