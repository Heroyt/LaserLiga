<?php

use App\Controllers\Api\Players;
use App\Controllers\Arenas;
use App\Controllers\Dashboard;
use App\Controllers\DistributionController;
use App\Controllers\EventController;
use App\Controllers\ForgotPassword;
use App\Controllers\Games;
use App\Controllers\Index;
use App\Controllers\Lang;
use App\Controllers\LeagueController;
use App\Controllers\Login;
use App\Controllers\MailTestController;
use App\Controllers\PushController;
use App\Controllers\Questionnaire;
use App\Controllers\TournamentController;
use App\Core\Middleware\CSRFCheck;
use Lsr\Core\Auth\Middleware\LoggedOut;
use Lsr\Core\Routing\Route;

// TODO: Remove test route
Route::get('mailtest/123', [MailTestController::class, 'sendTestMail']);
Route::get('mailtest/123/show', [MailTestController::class, 'showTestMail']);

Route::get('', [Index::class, 'show'])->name('index');

$gameGroup = Route::group('game');

$gameGroup->get('', [Games::class, 'show'])->name('game-empty');    // This will result in HTTP 404 error

$gameCodeGroup = $gameGroup->group('{code}');

$gameCodeGroup->get('', [Games::class, 'show'])->name('game')
              ->get('{user}', [Games::class, 'show'])->name('user-game')
              ->get('thumb', [Games::class, 'thumb'])
              ->get('highlights', [Games::class, 'highlights']);

$gameCodeGroup->group('player')->get('{id}', [Games::class, 'playerResults'])->get(
	'{id}/distribution/{param}',
	[
		DistributionController::class,
		'distribution',
	]
)->get('{id}/elo', [Games::class, 'eloInfo']);

$gameCodeGroup->group('team')->get('{id}', [Games::class, 'teamResults']);

$gameGroup->group('group')->group('{groupid}')->get('', [Games::class, 'group'])->name('group-results')->get(
	'thumb',
	[
		Games::class,
		'thumbGroup',
	]
);

// Alias to 'game' group
Route::group('g')
     ->get('', [Games::class, 'show'])->name('game-empty-alias') // This will result in HTTP 404 error
     ->get('abcdefghij', [Dashboard::class, 'bp'])->get('{code}', [Games::class, 'show'])->name('game-alias')->get(
	'{code}/thumb',
	[Games::class, 'thumb']
);

Route::group('players')
     ->get('find', [Players::class, 'find'])
     ->get('leaderboard', [Games::class, 'todayLeaderboard'])
     ->get('leaderboard/{system}', [Games::class, 'todayLeaderboard'])
     ->get('leaderboard/{system}/{date}', [Games::class, 'todayLeaderboard'])
     ->get('leaderboard/{system}/{date}/{property}', [Games::class, 'todayLeaderboard'])
     ->name('today-leaderboard');

Route::get('lang/{lang}', [Lang::class, 'setLang']);

// Questionnaire
Route::group('questionnaire')
     ->group('results')
     ->get('', [Questionnaire::class, 'resultsList'])
     ->name(
	     'questionnaire-results'
     )
     ->get('stats', [Questionnaire::class, 'resultsStats'])
     ->name('questionnaire-results-stats')
     ->get(
	     '{id}',
	     [
		     Questionnaire::class,
		     'resultsUser',
	     ]
     )
     ->name('questionnaire-results-user')
     ->endGroup()
     ->group('question')
     ->get('', [Questionnaire::class, 'getQuestion'])
     ->name('questionnaire-question')
     ->get('{key}', [Questionnaire::class, 'getQuestion'])
     ->endGroup()
     ->post('save', [Questionnaire::class, 'save'])
     ->name('questionnaire-save')
     ->post('done', [Questionnaire::class, 'done'])
     ->name('questionnaire-done')
     ->post('select', [Questionnaire::class, 'selectQuestionnaire'])
     ->post('select/{id}', [Questionnaire::class, 'selectQuestionnaire'])
     ->post('show_later', [Questionnaire::class, 'showLater'])
     ->post('dont_show', [Questionnaire::class, 'dontShowAgain']);

// Arena
Route::group('arena')
     ->get('', [Arenas::class, 'list'])
     ->name('arenas-list')
     ->group('{id}')
     ->get(
	     '',
	     [Arenas::class, 'show']
     )
     ->name('arenas-detail')
     ->group('tab')
	->get('stats', [Arenas::class, 'show'])->name('arena-detail-stats')
	->get('music', [Arenas::class, 'show'])->name('arena-detail-music')
	->get('games', [Arenas::class, 'show'])->name('arena-detail-games')
	->get('tournaments', [Arenas::class, 'show'])->name('arena-detail-tournaments')
	->get('info', [Arenas::class, 'show'])->name('arena-detail-info')
     ->endGroup()
     ->get('games', [Arenas::class, 'games'])
     ->group('stats')
     ->get('modes', [Arenas::class, 'gameModesStats'])
     ->get('music', [Arenas::class, 'musicModesStats'])
     ->endGroup()
     ->endGroup();

// Login
Route::group()->middlewareAll(new LoggedOut('dashboard'))->get('login', [Login::class, 'show'])->name('login')->post(
	'login',
	[Login::class, 'process']
)->get('login/forgot', [ForgotPassword::class, 'forgot'])->name('forgot-password')->post(
	'login/forgot',
	[
		ForgotPassword::class,
		'forgot',
	]
)->middleware(new CSRFCheck('forgot'))->get('login/forgot/reset', [ForgotPassword::class, 'reset'])->name(
	'reset-password'
)->post('login/forgot/reset', [ForgotPassword::class, 'reset'])->middleware(new CSRFCheck('reset'))->get(
	'register',
	[
		Login::class,
		'register',
	]
)->name('register')->post('register', [Login::class, 'processRegister']);

// Tournament
Route::group('tournament')
     ->get('', [TournamentController::class, 'show'])
     ->name('tournaments')
     ->get(
	     '{id}',
	     [
		     TournamentController::class,
		     'detail',
	     ]
     )
     ->name('tournament-detail')
     ->get('{id}/register', [TournamentController::class, 'register'])
     ->name(
	     'tournament-register'
     )
     ->post('{id}/register', [TournamentController::class, 'processRegister'])
     ->name('tournament-register-process')
     ->middleware(new CSRFCheck('tournament-register'))
     ->get('registration/{tournamentId}/{registration}', [TournamentController::class, 'updateRegistration'])
     ->name('tournament-register-update')
     ->get('registration/{tournamentId}/{registration}/{hash}', [TournamentController::class, 'updateRegistration'])
     ->name('tournament-register-update-2')
     ->post('registration/{tournamentId}/{registration}', [TournamentController::class, 'processUpdateRegister'])
     ->name('tournament-register-update-process')
     ->middleware(new CSRFCheck('tournament-update-register'));

Route::group('league')
     ->get('', [LeagueController::class, 'show'])
     ->name('leagues')
     ->get(
	     '{id}',
	     [
		     LeagueController::class,
		     'detail',
	     ]
     )
     ->get('{id}/register', [LeagueController::class, 'register'])
     ->name('league-register')
     ->post(
	     '{id}/register',
	     [
		     LeagueController::class,
		     'processRegister',
	     ]
     )
     ->name('league-register-process')
     ->middleware(new CSRFCheck('league-register'))
     ->get(
	     'team/{id}',
	     [
		     LeagueController::class,
		     'teamDetail',
	     ]
     )
     ->get('registration/{leagueId}/{registration}', [LeagueController::class, 'updateRegistration'])
     ->name(
	     'league-register-update'
     )
     ->get('registration/{leagueId}/{registration}/{hash}', [LeagueController::class, 'updateRegistration'])
     ->name(
	     'league-register-update-2'
     )
     ->post('registration/{leagueId}/{registration}', [LeagueController::class, 'processUpdateRegister'])
     ->name(
	     'league-register-update-process'
     )
     ->middleware(new CSRFCheck('league-update-register'))
     ->get(
	     '{id}/substitute',
	     [LeagueController::class, 'registerSubstitute']
     )
     ->name('league-register-substitute')
     ->post('{id}/substitute', [LeagueController::class, 'processSubstitute'])
     ->name('league-register-substitute-process')
     ->middleware(new CSRFCheck('league-register-substitute'));

// League - alias
Route::group('liga')
     ->get('', [LeagueController::class, 'show'])
     ->get('{slug}', [LeagueController::class, 'detailSlug'])
     ->get('{slug}/register', [LeagueController::class, 'registerSlug'])
     ->name('league-register-slug')
     ->post('{slug}/register', [LeagueController::class, 'processRegisterSlug'])
     ->name('league-register-process-slug')
     ->middleware(new CSRFCheck('league-register'))
     ->get('{slug}/substitute', [LeagueController::class, 'registerSubstituteSlug'])
     ->name('league-register-substitute-slug')
     ->post('{slug}/substitute', [LeagueController::class, 'processSubstituteSlug'])
     ->name('league-register-substitute-slug-process')
     ->middleware(new CSRFCheck('league-register-substitute'));

Route::group('events')
     ->get('', [EventController::class, 'show'])
     ->name('events')
     ->group('{id}')
     ->get('', [EventController::class, 'detail'])
     ->get('register', [EventController::class, 'register'])
     ->name('event-register')
     ->post('register', [EventController::class, 'processRegister'])
     ->name('event-register-process')
     ->middleware(new CSRFCheck('event-register'))
     ->endGroup()
     ->get('registration/{eventId}/{registration}', [EventController::class, 'updateRegistration'])
     ->name('event-register-update')
     ->get('registration/{eventId}/{registration}/{hash}', [EventController::class, 'updateRegistration'])
     ->name('event-register-update-2')
     ->post('registration/{eventId}/{registration}', [EventController::class, 'processUpdateRegister'])
     ->name('event-register-update-process')
     ->middleware(new CSRFCheck('event-update-register'));

// Push
Route::group('push')
     ->get('test', [PushController::class, 'sendTest'])
     ->get(
	     'subscribed',
	     [PushController::class, 'isSubscribed']
     )
     ->post('subscribe', [PushController::class, 'subscribe'])
     ->post('update', [PushController::class, 'updateUser'])
     ->post('unsubscribe', [PushController::class, 'unsubscribe']);