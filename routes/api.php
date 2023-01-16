<?php

use App\Controllers\Api\Games;
use App\Controllers\Api\Music;
use App\Core\Middleware\ApiToken;
use Lsr\Core\Routing\Route;

$apiToken = new ApiToken();

Route::group('/api')
		 ->middlewareAll($apiToken)
		 ->group('/games')
		 ->get('/', [Games::class, 'listGames'])
		 ->post('/', [Games::class, 'import'])
		 ->get('/{code}', [Games::class, 'getGame'])
		 ->group('/stats')
		 ->get('/', [Games::class, 'stats'])
		 ->endGroup()
		 ->endGroup()
		 ->group('/music')
		 ->post('/', [Music::class, 'import'])
		 ->delete('/{id}', [Music::class, 'removeMode'])
		 ->post('/{id}/upload', [Music::class, 'uploadFile'])
		 ->endGroup();
