<?php

use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;

/** @var Auth $auth */
$auth = App::getServiceByType(Auth::class);

if ($auth->loggedIn()) {
	return [
		[
			'name' => lang('Moje hry'),
			'icon' => 'fa-solid fa-gun',
			'route' => 'my-game-history',
		],
		[
			'name' => lang('Profil'),
			'icon' => 'fa-solid fa-user',
			'route' => 'dashboard',
		],
	];
}

return [
	[
		'name' => lang('Přihlásit'),
		'icon' => 'fa-solid fa-right-to-bracket',
		'route' => 'login',
	],
];