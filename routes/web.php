<?php

use App\Controllers\Api\Players;
use App\Controllers\Arenas;
use App\Controllers\Dashboard;
use App\Controllers\DistributionController;
use App\Controllers\EventController;
use App\Controllers\ForgotPassword;
use App\Controllers\Games;
use App\Controllers\Games\GameController;
use App\Controllers\Games\GameHighlightsController;
use App\Controllers\Games\GamePlayerController;
use App\Controllers\Games\GameTeamController;
use App\Controllers\Games\GameTodayLeaderboardController;
use App\Controllers\Games\GroupController;
use App\Controllers\Index;
use App\Controllers\Lang;
use App\Controllers\LeagueController;
use App\Controllers\Login;
use App\Controllers\MailTestController;
use App\Controllers\PrivacyController;
use App\Controllers\PushController;
use App\Controllers\Questionnaire;
use App\Controllers\TournamentController;
use App\Controllers\WellKnownController;
use App\Core\Middleware\CSRFCheck;
use Lsr\Core\App;
use Lsr\Core\Auth\Middleware\LoggedOut;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Routing\Router;


$auth = App::getService('auth');
assert($auth instanceof Auth);

/** @var Router $this */

// TODO: Remove test route
$this->get('mailtest/123', [MailTestController::class, 'sendTestMail']);
$this->get('mailtest/123/show', [MailTestController::class, 'showTestMail']);

$this->get('', [Index::class, 'show'])->name('index');

$this->get('zasady-zpracovani-osobnich-udaju', [PrivacyController::class, 'index'])->name('privacy-policy');
$this->get('privacy-policy', [PrivacyController::class, 'index']);

$gameGroup = $this->group('game');

$gameGroup->get('', [GameController::class, 'show'])->name('game-empty');    // This will result in HTTP 404 error

$gameCodeGroup = $gameGroup->group('{code}');

$gameCodeGroup->get('', [GameController::class, 'show'])->name('game')
              ->get('{user}', [GameController::class, 'show'])->name('user-game')
              ->get('thumb', [GameController::class, 'thumb'])
              ->get('thumb.png', [GameController::class, 'thumb'])
              ->get('highlights', [GameHighlightsController::class, 'show']);

$gameCodeGroup->group('player')
              ->group('{id}')
              ->get('', [GamePlayerController::class, 'show'])
              ->get('distribution/{param}', [DistributionController::class, 'distribution',])
              ->get('elo', [Games\GamePlayerEloController::class, 'show']);

$gameCodeGroup->group('team')
              ->get('{id}', [GameTeamController::class, 'show']);

$gameGroupGroup = $gameGroup->group('group');
$gameGroupIdGroup = $gameGroupGroup->group('{groupid}');
$gameGroupIdGroup->get('', [GroupController::class, 'group'])->name('group-results');
$gameGroupIdGroup->get('thumb', [GroupController::class, 'thumbGroup']);

// Alias to 'game' group
$this->group('g')
     ->get('', [GameController::class, 'show'])->name('game-empty-alias') // This will result in HTTP 404 error
     ->get('abcdefghij', [Dashboard::class, 'bp'])
     ->get('{code}', [GameController::class, 'show'])->name('game-alias')
     ->get('{code}/thumb', [GameController::class, 'thumb']);

$this->group('players')
     ->get('find', [Players::class, 'find'])
     ->get('leaderboard', [GameTodayLeaderboardController::class, 'show'])
     ->get('leaderboard/{system}', [GameTodayLeaderboardController::class, 'show'])
     ->get('leaderboard/{system}/{date}', [GameTodayLeaderboardController::class, 'show'])
     ->get('leaderboard/{system}/{date}/{property}', [GameTodayLeaderboardController::class, 'show'])
     ->name('today-leaderboard');

$this->get('lang/{lang}', [Lang::class, 'setLang']);

// Questionnaire
$this->group('questionnaire')
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
$this->group('arena')
     ->get('', [Arenas::class, 'list'])
     ->name('arenas-list')
     ->group('{id}')
     ->get('', [Arenas::class, 'show'])
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
$this->group()
     ->middlewareAll(new LoggedOut($auth, 'dashboard'))
     ->get('login', [Login::class, 'show'])
     ->name('login')
     ->post('login', [Login::class, 'process'])
     ->get('login/forgot', [ForgotPassword::class, 'forgot'])
     ->name('forgot-password')
     ->post('login/forgot', [ForgotPassword::class, 'forgot',])
     ->middleware(new CSRFCheck('forgot'))
     ->get('login/forgot/reset', [ForgotPassword::class, 'reset'])
     ->name('reset-password')
     ->post('login/forgot/reset', [ForgotPassword::class, 'reset'])
     ->middleware(new CSRFCheck('reset'))
     ->get('register', [Login::class, 'register',])
     ->name('register')
     ->post('register', [Login::class, 'processRegister']);

$this->get('login/confirm', [Login::class, 'confirm']);

// Tournament
$tournamentGroup = $this->group('tournament');
$tournamentGroup->get('', [TournamentController::class, 'show'])->name('tournaments');
$tournamentGroup->get('history', [TournamentController::class, 'history'])->name('tournament-history');
$tournamentIdGroup = $tournamentGroup->group('{id}');
$tournamentIdGroup->get('', [TournamentController::class, 'detail'])->name('tournament-detail');
$tournamentIdGroup->get('register', [TournamentController::class, 'register'])->name('tournament-register');
$tournamentIdGroup->post('register', [TournamentController::class, 'processRegister'])->name('tournament-register-process')->middleware(new CSRFCheck('tournament-register'));

$tournamentGroup->get('registration/{tournamentId}/{registration}', [TournamentController::class, 'updateRegistration'])
     ->name('tournament-register-update')
     ->get('registration/{tournamentId}/{registration}/{hash}', [TournamentController::class, 'updateRegistration'])
     ->name('tournament-register-update-2')
     ->post('registration/{tournamentId}/{registration}', [TournamentController::class, 'processUpdateRegister'])
     ->name('tournament-register-update-process')
     ->middleware(new CSRFCheck('tournament-update-register'));

$this->group('league')
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
$this->group('liga')
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

$eventsGroup = $this->group('events');
$eventsGroup->get('', [EventController::class, 'show'])->name('events');
$eventsGroup->get('history', [EventController::class, 'history'])->name('events-history');
$eventsIdGroup = $eventsGroup->group('{id}');
$eventsIdGroup->get('', [EventController::class, 'detail'])
     ->get('register', [EventController::class, 'register'])->name('event-register')
     ->post('register', [EventController::class, 'processRegister'])
     ->name('event-register-process')
     ->middleware(new CSRFCheck('event-register'));

$eventsGroup->get('registration/{eventId}/{registration}', [EventController::class, 'updateRegistration'])
     ->name('event-register-update')
     ->get('registration/{eventId}/{registration}/{hash}', [EventController::class, 'updateRegistration'])
     ->name('event-register-update-2')
     ->post('registration/{eventId}/{registration}', [EventController::class, 'processUpdateRegister'])
     ->name('event-register-update-process')
     ->middleware(new CSRFCheck('event-update-register'));

// Push
$this->group('push')
     ->get('test', [PushController::class, 'sendTest'])
     ->get(
	     'subscribed',
	     [PushController::class, 'isSubscribed']
     )
     ->post('subscribe', [PushController::class, 'subscribe'])
     ->post('update', [PushController::class, 'updateUser'])
     ->post('unsubscribe', [PushController::class, 'unsubscribe']);

// Well-known
$this->group('.well-known')
	->get('change-password', [WellKnownController::class, 'changePassword']);