<?php

use App\Controllers\Api\Games;
use App\Core\Middleware\ApiToken;
use Lsr\Core\Routing\Route;

$apiToken = new ApiToken();

Route::get('api/games', [Games::class, 'listGames'])
		 ->middleware($apiToken);
Route::get('api/games/{code}', [Games::class, 'getGame'])
		 ->middleware($apiToken);
Route::post('api/games', [Games::class, 'import'])
		 ->middleware($apiToken);