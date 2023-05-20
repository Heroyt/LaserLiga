<?php

use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;

/** @var Auth $auth */
$auth = App::getServiceByType(Auth::class);

$nav = [
	[
		'name' => lang('Arény'),
		'icon' => 'fa-solid fa-location-dot',
		'route' => 'arenas-list',
	],
];

if ($auth->loggedIn()) {
	$nav[] = [
		'name' => lang('Žebříček'),
		'icon' => 'fa-solid fa-ranking-star',
		'route' => 'player-leaderboard',
	];
}

return $nav;