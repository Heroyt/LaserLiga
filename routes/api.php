<?php

use App\Controllers\Api\Games;
use App\Controllers\Api\Music;
use App\Core\Middleware\ApiToken;
use Lsr\Core\Routing\Route;

$apiToken = new ApiToken();

Route::get('api/games', [Games::class, 'listGames'])
		 ->middleware($apiToken);
Route::get('api/games/{code}', [Games::class, 'getGame'])
		 ->middleware($apiToken);
Route::post('api/games', [Games::class, 'import'])
		 ->middleware($apiToken);

Route::post('api/music', [Music::class, 'import'])
		 ->middleware($apiToken);
Route::post('api/music/{id}/upload', [Music::class, 'uploadFile'])
		 ->middleware($apiToken);
Route::delete('api/music/{id}', [Music::class, 'removeMode'])
		 ->middleware($apiToken);