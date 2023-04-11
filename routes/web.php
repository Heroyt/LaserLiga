<?php

use App\Controllers\Api\Players;
use App\Controllers\Arenas;
use App\Controllers\Dashboard;
use App\Controllers\ForgotPassword;
use App\Controllers\Games;
use App\Controllers\Index;
use App\Controllers\Lang;
use App\Controllers\LeagueController;
use App\Controllers\Login;
use App\Controllers\MailTestController;
use App\Controllers\Questionnaire;
use App\Controllers\TournamentController;
use App\Core\Middleware\CSRFCheck;
use Lsr\Core\Auth\Middleware\LoggedOut;
use Lsr\Core\Routing\Route;

// TODO: Remove test route
Route::get('/mailtest/123', [MailTestController::class, 'sendTestMail']);
Route::get('/mailtest/123/show', [MailTestController::class, 'showTestMail']);

Route::get('/', [Index::class, 'show'])->name('index');

Route::group('/game')
		 ->get('/', [Games::class, 'show'])->name('game-empty')    // This will result in HTTP 404 error
		 ->get('/{code}', [Games::class, 'show'])->name('game');

Route::group('/g')
		 ->get('/', [Games::class, 'show'])->name('game-empty-alias') // This will result in HTTP 404 error
		 ->get('/abcdefghij', [Dashboard::class, 'bp'])
		 ->get('/{code}', [Games::class, 'show'])->name('game-alias');

Route::group('/players')
		 ->get('/find', [Players::class, 'find'])
		 ->get('/leaderboard', [Games::class, 'todayLeaderboard'])
		 ->get('/leaderboard/{system}', [Games::class, 'todayLeaderboard'])
		 ->get('/leaderboard/{system}/{date}', [Games::class, 'todayLeaderboard'])
		 ->get('/leaderboard/{system}/{date}/{property}', [Games::class, 'todayLeaderboard'])->name('today-leaderboard');

Route::get('/lang/{lang}', [Lang::class, 'setLang']);

// Questionnaire
Route::group('/questionnaire')
		 ->group('/results')
		 ->get('/', [Questionnaire::class, 'resultsList'])->name('questionnaire-results')
		 ->get('/stats', [Questionnaire::class, 'resultsStats'])->name('questionnaire-results-stats')
		 ->get('/{id}', [Questionnaire::class, 'resultsUser'])->name('questionnaire-results-user')
		 ->endGroup()
		 ->group('/question')
		 ->get('/', [Questionnaire::class, 'getQuestion'])->name('questionnaire-question')
		 ->get('/{key}', [Questionnaire::class, 'getQuestion'])
		 ->endGroup()
		 ->post('/save', [Questionnaire::class, 'save'])->name('questionnaire-save')
		 ->post('/done', [Questionnaire::class, 'done'])->name('questionnaire-done')
		 ->post('/select', [Questionnaire::class, 'selectQuestionnaire'])
		 ->post('/select/{id}', [Questionnaire::class, 'selectQuestionnaire'])
		 ->post('/show_later', [Questionnaire::class, 'showLater'])
		 ->post('/dont_show', [Questionnaire::class, 'dontShowAgain']);

// Arena
Route::group('/arena')
		 ->get('/', [Arenas::class, 'list'])->name('arenas-list')
		 ->group('/{id}')
		 ->get('/', [Arenas::class, 'show'])->name('arenas-detail')
		 ->get('/games', [Arenas::class, 'games'])
		 ->group('/stats')
		 ->get('/modes', [Arenas::class, 'gameModesStats'])
		 ->get('/music', [Arenas::class, 'musicModesStats'])
		 ->endGroup()
		 ->endGroup();

// Login
Route::group()
		 ->middlewareAll(new LoggedOut('dashboard'))
		 ->get('/login', [Login::class, 'show'])->name('login')
		 ->post('/login', [Login::class, 'process'])
		 ->get('/login/forgot', [ForgotPassword::class, 'forgot'])->name('forgot-password')
		 ->post('/login/forgot', [ForgotPassword::class, 'forgot'])->middleware(new CSRFCheck('forgot'))
		 ->get('/login/forgot/reset', [ForgotPassword::class, 'reset'])->name('reset-password')
		 ->post('/login/forgot/reset', [ForgotPassword::class, 'reset'])->middleware(new CSRFCheck('reset'))
		 ->get('/register', [Login::class, 'register'])->name('register')
		 ->post('/register', [Login::class, 'processRegister']);

// Tournament
Route::group('/tournament')
		 ->get('/{id}', [TournamentController::class, 'detail'])
		 ->get('/{id}/register', [TournamentController::class, 'register'])->name('tournament-register')
		 ->post('/{id}/register', [TournamentController::class, 'processRegister'])->name('tournament-register-process')->middleware(new CSRFCheck('tournament-register'))
		 ->get('/registration/{tournamentId}/{registration}', [TournamentController::class, 'updateRegistration'])->name('tournament-register-update')
		 ->post('/registration/{tournamentId}/{registration}', [TournamentController::class, 'processUpdateRegister'])->name('tournament-register-update-process')->middleware(new CSRFCheck('tournament-update-register'));

Route::group('/league')
		 ->get('/{id}', [LeagueController::class, 'detail']);