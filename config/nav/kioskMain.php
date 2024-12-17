<?php

use App\Services\FontAwesomeManager;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;

/** @var Auth $auth */
$auth = App::getServiceByType(Auth::class);

$fontawesome = App::getService('fontawesome');
assert($fontawesome instanceof FontAwesomeManager, 'Invalid service type from DI');

$nav = [
	[
		'name' => lang('Arény'),
		'icon' => $fontawesome->solid('location-dot'),
		'route' => 'arenas-list',
		'path' => ['arena'],
	],
];

if ($auth->loggedIn()) {
	$nav[] = [
		'name' => lang('Žebříček'),
		'icon' => $fontawesome->solid('ranking-star'),
		'route' => 'player-leaderboard',
	];
}

return $nav;