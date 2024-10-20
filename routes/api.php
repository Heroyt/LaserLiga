<?php

use App\Controllers\Api\DevController;
use App\Controllers\Api\Games;
use App\Controllers\Api\Import;
use App\Controllers\Api\LeaguesController;
use App\Controllers\Api\Music;
use App\Controllers\Api\Players;
use App\Controllers\Api\TournamentsController;
use App\Controllers\Api\VestController;
use App\Controllers\User\UserGameController;
use App\Core\Middleware\ApiToken;
use Lsr\Core\Routing\Route;

$apiToken = new ApiToken();

$apiGroup = Route::group('api')->middlewareAll($apiToken);

// Games
$gamesGroup = $apiGroup->group('games')->get('', [Games::class, 'listGames'])->post('', [Games::class, 'import'])->get(
	'skills',
	[Games::class, 'recalcMultipleGameSkills']
);

$gamesGroup->group('{code}')->get('', [Games::class, 'getGame'])->get('highlights', [Games::class, 'highlights'])->get(
	'users',
	[Games::class, 'getGameUsers']
)->get('skills', [Games::class, 'recalcGameSkill'])->get('recalc', [Games::class, 'recalcGame']);

$gamesGroup->group('stats')->get('', [Games::class, 'stats']);

// Import
$apiGroup->group('import')->post('', [Import::class, 'parse']);

// Tournaments

// Keeping this path for legacy reasons - Should not be used
$tournamentGroup = $apiGroup->group('tournament')->get('', [TournamentsController::class, 'getAll']);

$tournamentGroup->group('{id}')->get('', [TournamentsController::class, 'get'])->post(
	'',
	[
		TournamentsController::class,
		'syncGames',
	]
)->get('teams', [TournamentsController::class, 'getTournamentTeams']);

$tournamentsGroup = $apiGroup->group('tournaments')->get('', [TournamentsController::class, 'getAll'])->post(
	'',
	[
		TournamentsController::class,
		'syncGames',
	]
);

$tournamentsGroup->group('{id}')->get('', [TournamentsController::class, 'get'])->get(
	'teams',
	[
		TournamentsController::class,
		'getTournamentTeams',
	]
);

// Leagues

// Keeping this path for legacy reasons - Should not be used
$leagueGroup = $apiGroup->group('league')->get('', [LeaguesController::class, 'getAll']);

$leagueGroup->group('{id}')->get('', [LeaguesController::class, 'get'])->get(
	'points',
	[LeaguesController::class, 'recountPoints']
)->post('fixplayers', [LeaguesController::class, 'fixLeaguePlayers'])->get(
	'tournaments',
	[
		LeaguesController::class,
		'getTournaments',
	]
);

$leaguesGroup = $apiGroup->group('leagues')->get('', [LeaguesController::class, 'getAll']);

$leaguesGroup->group('{id}')->get('', [LeaguesController::class, 'get'])->get(
	'points',
	[
		LeaguesController::class,
		'recountPoints',
	]
)->post('fixplayers', [LeaguesController::class, 'fixLeaguePlayers'])->get(
	'tournaments',
	[
		LeaguesController::class,
		'getTournaments',
	]
);

// Music

$apiGroup->group('music')->post('', [Music::class, 'import'])->delete('{id}', [Music::class, 'removeMode'])->post(
	'{id}/upload',
	[Music::class, 'uploadFile']
);

// Players

$playersGroup = $apiGroup->group('players')->get('', [Players::class, 'find']);

$playersGroup->group('{code}')->get('', [Players::class, 'player'])->get('title', [Players::class, 'playerTitle']);

// Vests
$apiGroup->group('vests')->get('', [VestController::class, 'getVests'])->post('', [VestController::class, 'syncVests']);

// Dev tools

$devToolGroup = $apiGroup->group('devtools')->post('regression', [DevController::class, 'updateRegressionModels'])->get(
	'sitemap',
	[DevController::class, 'generateSitemap']
);

$devToolGroup->group('users')->get('stats', [UserGameController::class, 'updateAllUsersStats'])->get(
	'{id}/stats',
	[
		UserGameController::class,
		'updateStats',
	]
)->get('dateRanks', [UserGameController::class, 'calculateDayRanks']);

$devToolGroup->group('game')->post('modes', [DevController::class, 'assignGameModes'])->post(
	'relativehits',
	[
		DevController::class,
		'relativeHits',
	]
);

$devToolGroup->group('images')->post('optimize', [DevController::class, 'generateOptimizedUploads']);

$devToolGroup->group('test')->get('gender', [DevController::class, 'genderTest'])->get(
	'inflection',
	[
		DevController::class,
		'inflectionTest',
	]
)->get('achievement', [DevController::class, 'achievementCheckerTest']);
