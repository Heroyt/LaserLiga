<?php

use App\Models\Auth\User;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;

$nav = [
	[
		'name' => lang('Žebříček'),
		'route' => 'player-leaderboard',
		'icon' => 'fa-solid fa-ranking-star',
	],
	[
		'name' => lang('Moje hry'),
		'route' => 'my-game-history',
		'icon' => 'fa-solid fa-gun',
	],
];

/** @var User $user */
$user = App::getServiceByType(Auth::class)->getLoggedIn();

if (!empty($user->player?->getTournaments() ?? [])) {
	$nav[] = [
		'name' => lang('Moje turnaje'),
		'route' => 'my-tournaments',
		'icon' => 'fa-solid fa-trophy',
	];
}

return $nav;