<?php

use App\Controllers\Api\DevController;
use App\Controllers\Api\Games;
use App\Controllers\Api\LeaguesController;
use App\Controllers\Api\Music;
use App\Controllers\Api\Players;
use App\Controllers\Api\TournamentsController;
use App\Controllers\User\UserGameController;
use App\Core\Middleware\ApiToken;
use Lsr\Core\Routing\Route;

$apiToken = new ApiToken();

$apiGroup = Route::group('/api')->middlewareAll($apiToken);

$apiGroup->group('/games')
				 ->get('/', [Games::class, 'listGames'])
				 ->post('/', [Games::class, 'import'])
				 ->get('/{code}', [Games::class, 'getGame'])
				 ->get('/{code}/users', [Games::class, 'getGameUsers'])
				 ->get('/{code}/skills', [Games::class, 'recalcGameSkill'])
				 ->get('/skills', [Games::class, 'recalcMultipleGameSkills'])
				 ->group('/stats')
				 ->get('/', [Games::class, 'stats'])
				 ->endGroup();

$apiGroup->group('/tournament')
				 ->get('/', [TournamentsController::class, 'getAll'])
				 ->get('/{id}', [TournamentsController::class, 'get'])
				 ->post('/{id}', [TournamentsController::class, 'syncGames'])
				 ->get('/{id}/teams', [TournamentsController::class, 'getTournamentTeams']);

$apiGroup->group('/tournaments')
				 ->get('/', [TournamentsController::class, 'getAll'])
				 ->get('/{id}', [TournamentsController::class, 'get'])
				 ->get('/{id}/teams', [TournamentsController::class, 'getTournamentTeams']);

$apiGroup->group('league')
         ->get('', [LeaguesController::class, 'getAll'])
         ->get('{id}', [LeaguesController::class, 'get'])
         ->get('{id}/points', [LeaguesController::class, 'recountPoints'])
         ->get('{id}/tournaments', [LeaguesController::class, 'getTournaments']);

$apiGroup->group('/music')
	->post('/', [Music::class, 'import'])
	->delete('/{id}', [Music::class, 'removeMode'])
	->post('/{id}/upload', [Music::class, 'uploadFile'])
	->endGroup();

$apiGroup->group('/players')
				 ->get('/', [Players::class, 'find'])
				 ->get('/{code}', [Players::class, 'player'])
				 ->endGroup();

$apiGroup->group('/devtools')
				 ->post('/users/stats', [UserGameController::class, 'updateAllUsersStats'])
				 ->post('/users/{id}/stats', [UserGameController::class, 'updateStats'])
				 ->get('/users/dateRanks', [UserGameController::class, 'calculateDayRanks'])
				 ->post('/relativehits', [DevController::class, 'relativeHits'])
				 ->post('/game/modes', [DevController::class, 'assignGameModes'])
				 ->post('/regression', [DevController::class, 'updateRegressionModels']);
