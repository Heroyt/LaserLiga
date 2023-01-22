<?php

use App\Controllers\Arenas;
use App\Controllers\Dashboard;
use App\Controllers\Games;
use App\Controllers\Index;
use App\Controllers\Lang;
use App\Controllers\Questionnaire;
use Lsr\Core\Routing\Route;

Route::get('/', [Index::class, 'show'])->name('index');

Route::group('/game')
		 ->get('/', [Games::class, 'show'])->name('game-empty')    // This will result in HTTP 404 error
		 ->get('/{game}', [Games::class, 'show'])->name('game');

Route::group('/g')
		 ->get('/', [Games::class, 'show'])->name('game-empty-alias') // This will result in HTTP 404 error
		 ->get('/abcdefghij', [Dashboard::class, 'bp'])
		 ->get('/{game}', [Games::class, 'show'])->name('game-alias');

Route::group('/players')
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
		 ->group('/stats')
		 ->get('/modes', [Arenas::class, 'gameModesStats'])
		 ->get('/music', [Arenas::class, 'musicModesStats'])
		 ->endGroup()
		 ->endGroup();