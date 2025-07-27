<?php
declare(strict_types=1);

use App\Controllers\Dashboard;
use App\Controllers\DistributionController;
use App\Controllers\Games\GameController;
use App\Controllers\Games\GameHighlightsController;
use App\Controllers\Games\GamePlayerController;
use App\Controllers\Games\GamePlayerEloController;
use App\Controllers\Games\GameTeamController;
use App\Controllers\Games\GroupController;
use App\Core\Middleware\CacheControl;
use App\Core\Middleware\ContentLanguageHeader;
use App\Core\Middleware\NoCacheControl;
use Lsr\Core\Middleware\DefaultLanguageRedirect;
use Lsr\Core\Routing\Router;

/** @var Router $this */

$noCacheControl = new NoCacheControl();
$cacheControl7days = new CacheControl(604800);
$cacheControl1Day = new CacheControl(86400);

// /game
$routes = $this
	->group('[lang=cs]')
	->middlewareAll(new DefaultLanguageRedirect(), new ContentLanguageHeader());
$gameGroup = $routes->group('game');

$gameGroup->get('', [GameController::class, 'show'])->name('game-empty');    // This will result in HTTP 404 error

// /game/{code}
$gameCodeGroup = $gameGroup->group('{code}');

$gameCodeGroup->get('', [GameController::class, 'show'])
              ->name('game')
              ->middleware($noCacheControl);
$gameCodeGroup->get('{user}', [GameController::class, 'show'])
              ->name('user-game')
              ->middleware($noCacheControl);
$gameCodeGroup->get('thumb', [GameController::class, 'thumb'])
              ->middleware($cacheControl7days); // 7 days
$gameCodeGroup->get('thumb.png', [GameController::class, 'thumb'])
              ->middleware($cacheControl7days); // 7 days
$gameCodeGroup->get('highlights', [GameHighlightsController::class, 'show'])
              ->middleware($cacheControl1Day); // 1 day

$gameCodeGroup->get('photos', [GameController::class, 'downloadPhotos']);
$gameCodeGroup->post('photos/public', [GameController::class, 'makePublic']);
$gameCodeGroup->post('photos/hidden', [GameController::class, 'makeHidden']);

// /game/{code}/player
$gameCodePlayerGroup = $gameCodeGroup->group('player');

// /game/{code}/player/{id}
$gameCodePlayerIdGroup = $gameCodePlayerGroup->group('{id}');

$gameCodePlayerIdGroup->get('', [GamePlayerController::class, 'show'])
                      ->middleware($noCacheControl);
$gameCodePlayerIdGroup->get('distribution/{param}', [DistributionController::class, 'distribution'])
                      ->middleware($cacheControl1Day); // 1 day;
$gameCodePlayerIdGroup->get('elo', [GamePlayerEloController::class, 'show'])
                      ->middleware($cacheControl1Day); // 1 day;

// /game/{code}/team
$gameCodeTeamGroup = $gameCodeGroup->group('team');
$gameCodeTeamGroup->get('{id}', [GameTeamController::class, 'show'])
                  ->middleware($noCacheControl);

// /game/group
$gameGroupGroup = $gameGroup->group('group');

// /game/group/{groupid}
$gameGroupIdGroup = $gameGroupGroup->group('{groupid}');

$gameGroupIdGroup->get('', [GroupController::class, 'group'])
                 ->name('group-results')
                 ->middleware($noCacheControl);
$gameGroupIdGroup->get('thumb', [GroupController::class, 'thumbGroup'])
                 ->middleware($cacheControl7days);

$gameGroupIdGroup->get('photos', [GroupController::class, 'downloadPhotos']);
$gameGroupIdGroup->post('photos/public', [GroupController::class, 'makePublic']);
$gameGroupIdGroup->post('photos/hidden', [GroupController::class, 'makeHidden']);

// Alias to 'game' group
// /g
$routes->group('g')
       ->get('', [GameController::class, 'show'])->name('game-empty-alias') // This will result in HTTP 404 error
       ->get('abcdefghij', [Dashboard::class, 'bp'])
       ->get('{code}', [GameController::class, 'show'])->name('game-alias')->middleware($noCacheControl)
       ->get('{code}/photos', [GameController::class, 'downloadPhotos'])
       ->get('{code}/thumb', [GameController::class, 'thumb'])->middleware($cacheControl7days);