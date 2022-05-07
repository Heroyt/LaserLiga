<?php

use App\Controllers\Dashboard;
use App\Controllers\Games;
use App\Controllers\Questionnaire;
use App\Core\App;
use App\Core\Request;
use App\Core\Routing\Route;

Route::get('/', [Dashboard::class, 'show'])->name('dashboard');

Route::get('/game', [Games::class, 'show'])->name('game-empty');    // This will result in HTTP 404 error
Route::get('/g', [Games::class, 'show'])->name('game-empty-alias'); // This will result in HTTP 404 error
Route::get('/game/{game}', [Games::class, 'show'])->name('game');
Route::get('/g/abcdefghij', static function() {
	header('location: https://youtu.be/dQw4w9WgXcQ');
	exit;
});
Route::get('/g/{game}', [Games::class, 'show'])->name('game-alias');
Route::get('players/leaderboard', [Games::class, 'todayLeaderboard']);
Route::get('players/leaderboard/{system}', [Games::class, 'todayLeaderboard']);
Route::get('players/leaderboard/{system}/{date}', [Games::class, 'todayLeaderboard']);
Route::get('players/leaderboard/{system}/{date}/{property}', [Games::class, 'todayLeaderboard'])->name('today-leaderboard');

Route::get('/lang/{lang}', static function(Request $request) {
	$_SESSION['lang'] = $request->params['lang'];
	App::redirect($request->get['redirect'] ?? []);
});

// Questionnaire
Route::get('questionnaire/results', [Questionnaire::class, 'resultsList'])->name('questionnaire-results');
Route::get('questionnaire/results/stats', [Questionnaire::class, 'resultsStats'])->name('questionnaire-results-stats');
Route::get('questionnaire/results/{id}', [Questionnaire::class, 'resultsUser'])->name('questionnaire-results-user');
Route::get('questionnaire/question', [Questionnaire::class, 'getQuestion'])->name('questionnaire-question');
Route::get('questionnaire/question/{key}', [Questionnaire::class, 'getQuestion']);
Route::post('questionnaire/save', [Questionnaire::class, 'save'])->name('questionnaire-save');
Route::post('questionnaire/done', [Questionnaire::class, 'done'])->name('questionnaire-done');
Route::post('questionnaire/select', [Questionnaire::class, 'selectQuestionnaire']);
Route::post('questionnaire/select/{id}', [Questionnaire::class, 'selectQuestionnaire']);
Route::post('questionnaire/show_later', [Questionnaire::class, 'showLater']);
Route::post('questionnaire/dont_show', [Questionnaire::class, 'dontShowAgain']);