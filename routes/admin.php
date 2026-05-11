<?php

use App\Controllers\Admin\Arena\PhotosController;
use App\Controllers\Admin\Arena\UsersController;
use App\Controllers\Admin\Arenas;
use App\Controllers\Admin\Booking\BookingAdminController;
use App\Controllers\Admin\Booking\BookingSettingsController;
use App\Controllers\Admin\Debug;
use App\Controllers\Admin\Games;
use App\Controllers\Admin\TournamentStats;
use App\Core\Middleware\CanManageArena;
use App\Core\Middleware\ContentLanguageHeader;
use App\Core\Middleware\LoggedIn;
use App\Core\ParamValidators\ModelIdValidator;
use App\Models\Booking\Booking;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Middleware\DefaultLanguageRedirect;
use Lsr\Core\Routing\Router;

/** @var Router $this */

$langGroup = $this
	->group('[lang=cs]')
	->middlewareAll(new DefaultLanguageRedirect(), new ContentLanguageHeader());
$adminGroup = $langGroup->group('/admin');

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
           ->get('on', [Debug::class, 'turnOnTracy'])->name('tracy-on')
           ->get('off', [Debug::class, 'turnOffTracy'])->name('tracy-off');

$arenasAdminGroup = $adminGroup->group('/arenas')
                               ->middlewareAll($arenasMiddleware);

$arenasAdminGroup->get('', [Arenas::class, 'show'])->name('admin-arenas')
                 ->post('', [Arenas::class, 'create'])
                 ->post('/apikey/{id}/invalidate', [Arenas::class, 'invalidateApiKey']);

$arenaIdAdminGroup = $arenasAdminGroup->group('{arenaId}');
$arenaIdAdminGroup->get('edit', [Arenas::class, 'edit'])->name('admin-arenas-edit')->middleware($manageArena)
                  ->post('edit', [Arenas::class, 'process'])->middleware($manageArena)
                  ->post('image', [Arenas::class, 'imageUpload'])->middleware($manageArena)
                  ->post('apikey', [Arenas::class, 'generateApiKey'])->middleware($manageArena);

$arenasAdminGroup->group('{arenaId}/users')
                 ->middlewareAll($viewArena, new LoggedIn($auth, ['manage-arena-users']))
                 ->get('', [UsersController::class, 'show'])->name('admin-arenas-users')
                 ->get('find', [UsersController::class, 'findUsers'])
                 ->post('{player}', [UsersController::class, 'updateUser']);

$arenasAdminPhotosGroup = $arenasAdminGroup->group('{arenaId}/photos')
                                           ->middlewareAll($photosMiddleware)
                                           ->get('', [PhotosController::class, 'show'])->name('admin-arenas-photos')
                                           ->post('download', [PhotosController::class, 'downloadPhotos'])
                                           ->post('{code}', [PhotosController::class, 'assignPhotos'])
                                           ->post('unassign', [PhotosController::class, 'unassignPhotos'])
                                           ->post('secret', [PhotosController::class, 'setPhotoSecret'])
                                           ->post('public', [PhotosController::class, 'setPhotoPublic'])
                                           ->post('{code}/mail', [PhotosController::class, 'sendPhotoMail'])
                                           ->delete('delete/{photoId}', [PhotosController::class, 'deletePhoto'])
                                           ->delete('', [PhotosController::class, 'deletePhotos'])
                                           ->post('upload', [PhotosController::class, 'uploadPhotos']);


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

$bookingSettingsGroup = $arenaIdAdminGroup->group('booking-settings')
	->middleware(new LoggedIn($auth, ['manage-booking']));
$bookingSettingsGroup->get('', [BookingSettingsController::class, 'settings'])->name('booking-settings');
$bookingSettingsGroup->post('', [BookingSettingsController::class, 'saveSettings']);

$bookingTypeGroup = $bookingSettingsGroup->group('booking-type');
$bookingTypeGroup->post('', [BookingSettingsController::class, 'createType']);

$bookingTypeIdGroup = $bookingTypeGroup->group('{type}');
$bookingTypeIdGroup->delete('', [BookingSettingsController::class, 'deleteType']);

$bookingSubtypeGroup = $bookingTypeIdGroup->group('subtype');
$bookingSubtypeGroup->post('', [BookingSettingsController::class, 'createSubtype']);

$bookingSubtypeIdGroup = $bookingSubtypeGroup->group('{subtype}');
$bookingSubtypeIdGroup->delete('', [BookingSettingsController::class, 'deleteSubtype']);

$bookingGroup = $arenaIdAdminGroup->group('booking')
                                  ->middlewareAll(
	                                  new LoggedIn($auth, [['manage-booking', 'view-booking']]),
	                                  new CanManageArena([['manage-booking', 'view-booking']])
                                  );

$editBookingMiddleware = new LoggedIn($auth, [['manage-booking', 'edit-booking']]);

$bookingGroup->get('', [BookingAdminController::class, 'show'])->name('admin-booking');
$bookingGroup->get('calendar/{type}', [BookingAdminController::class, 'calendar']);
$bookingGroup->post('note/{type}', [BookingAdminController::class, 'editNote']);
$bookingGroup->get('create/{type}', [BookingAdminController::class, 'create'])->middleware($editBookingMiddleware)->name('admin-booking-create');
$bookingGroup->post('create/{type}', [BookingAdminController::class, 'store'])->middleware($editBookingMiddleware);

$bookingGroup->group('{booking}')
             ->param('booking', new ModelIdValidator(Booking::class))
             ->get('', [BookingAdminController::class, 'getBooking'])
             ->get('edit', [BookingAdminController::class, 'edit'])->middleware($editBookingMiddleware)->name('admin-booking-edit')
             ->post('edit', [BookingAdminController::class, 'update'])->middleware($editBookingMiddleware)
             ->delete('', [BookingAdminController::class, 'deleteBooking'])->middleware($editBookingMiddleware)
             ->delete('{slot}', [BookingAdminController::class, 'deleteBookingSlot'])->middleware($editBookingMiddleware);