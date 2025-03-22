<?php

use App\Controllers\Admin\Arena\PhotosController;
use App\Controllers\Admin\Arenas;
use App\Controllers\Admin\Debug;
use App\Controllers\Admin\Games;
use App\Controllers\Admin\TournamentStats;
use App\Core\Middleware\CanManageArena;
use App\Core\Middleware\LoggedIn;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Routing\Router;

/** @var Router $this */

$adminGroup = $this->group('/admin');

$auth = App::getService('auth');
assert($auth instanceof Auth);

// Arenas
$arenasMiddleware = new LoggedIn($auth, ['view-arena']);
$gamesMiddleware = new LoggedIn($auth, ['manage-games']);
$tournamentMiddleware = new LoggedIn($auth, ['manage-tournaments']);
$manageArena = new CanManageArena([['manage-arena', 'edit-arena']]);
$viewArena = new CanManageArena([['view-arena', 'manage-arena']]);
$photosMiddleware = new CanManageArena([['manage-photos', 'manage-arena']]);

$adminGroup->group('tracy')
           ->middlewareAll(new LoggedIn($auth, ['debug']))
           ->get('on', [Debug::class, 'turnOnTracy'])
           ->get('off', [Debug::class, 'turnOffTracy']);

$arenasAdminGroup = $adminGroup->group('/arenas')
                               ->middlewareAll($arenasMiddleware);

$arenasAdminGroup->get('', [Arenas::class, 'show'])->name('admin-arenas')
                 ->post('', [Arenas::class, 'create'])
                 ->post('/apikey/{id}/invalidate', [Arenas::class, 'invalidateApiKey']);

$arenasAdminGroup->group('{arenaId}')
                 ->get('edit', [Arenas::class, 'edit'])->name('admin-arenas-edit')->middleware($manageArena)
                 ->post('edit', [Arenas::class, 'process'])->middleware($manageArena)
                 ->post('image', [Arenas::class, 'imageUpload'])->middleware($manageArena)
                 ->post('apikey', [Arenas::class, 'generateApiKey'])->middleware($manageArena)
                 ->get('photos', [PhotosController::class, 'show'])->name('admin-arenas-photos')->middleware($photosMiddleware)
                 ->post('photos/download', [PhotosController::class, 'downloadPhotos'])->middleware($photosMiddleware)
                 ->post('photos/{code}', [PhotosController::class, 'assignPhotos'])->middleware($photosMiddleware)
                 ->post('photos/unassign', [PhotosController::class, 'unassignPhotos'])->middleware($photosMiddleware)
                 ->post('photos/secret', [PhotosController::class, 'setPhotoSecret'])->middleware($photosMiddleware)
                 ->post('photos/public', [PhotosController::class, 'setPhotoPublic'])->middleware($photosMiddleware)
                 ->post('photos/{code}/mail', [PhotosController::class, 'sendPhotoMail'])->middleware($photosMiddleware)
                 ->delete('photos/delete/{photoId}', [PhotosController::class, 'deletePhoto'])->middleware($photosMiddleware)
                 ->delete('photos', [PhotosController::class, 'deletePhotos'])->middleware($photosMiddleware);

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