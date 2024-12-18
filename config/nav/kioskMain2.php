<?php

use App\Services\FontAwesomeManager;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;

/** @var Auth $auth */
$auth = App::getServiceByType(Auth::class);

$fontawesome = App::getService('fontawesome');
assert($fontawesome instanceof FontAwesomeManager, 'Invalid service type from DI');

if ($auth->loggedIn()) {
	return [
		[
			'name' => lang('Profil'),
			'icon' => $fontawesome->solid('user'),
			'route' => 'dashboard',
		],
	];
}

return [
	[
		'name' => lang('Přihlásit'),
		'icon' => $fontawesome->solid('right-to-bracket'),
		'route' => 'login',
	],
];