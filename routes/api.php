<?php

use App\Controllers\Api\Games;
use App\Controllers\Api\Music;
use App\Controllers\Api\Players;
use App\Core\Middleware\ApiToken;
use Lsr\Core\Routing\Route;

$apiToken = new ApiToken();

$apiGroup = Route::group('/api')->middlewareAll($apiToken);

$apiGroup->group('/games')
				 ->get('/', [Games::class, 'listGames'])
				 ->post('/', [Games::class, 'import'])
				 ->get('/{code}', [Games::class, 'getGame'])
				 ->group('/stats')
				 ->get('/', [Games::class, 'stats'])
				 ->endGroup();

$apiGroup->group('/music')
				 ->post('/', [Music::class, 'import'])
				 ->delete('/{id}', [Music::class, 'removeMode'])
				 ->post('/{id}/upload', [Music::class, 'uploadFile']);

$apiGroup->group('/players')
				 ->get('/', [Players::class, 'find'])
				 ->get('/{id}', [Players::class, 'player'])
				 ->endGroup();
