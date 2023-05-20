<?php

use App\Models\Auth\User;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;

$nav = [
	[
		'name' => lang('Žebříček'),
		'route' => 'player-leaderboard',
	],
	[
		'name' => lang('Moje hry'),
		'route' => 'my-game-history',
	],
];

/** @var User $user */
$user = App::getServiceByType(Auth::class)->getLoggedIn();

if (!empty($user->player?->getTournaments() ?? [])) {
	$nav[] = [
		'name' => lang('Moje turnaje'),
		'route' => 'my-tournaments',
	];
}

return $nav;