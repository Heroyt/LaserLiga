<?php

use App\Models\Auth\User;
use App\Services\FontAwesomeManager;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;


$nav = [];

/** @var User $user */
$user = App::getServiceByType(Auth::class)->getLoggedIn();

$fontawesome = App::getService('fontawesome');
assert($fontawesome instanceof FontAwesomeManager, 'Invalid service type from DI');

if (!empty($user->player?->getTournaments() ?? [])) {
	$nav[] = [
		'name' => lang('Moje turnaje'),
		'icon' => $fontawesome->solid('trophy'),
		'route' => 'my-tournaments',
	];
}

$nav[] = [
	'name' => lang('Nastavení'),
	'icon' => $fontawesome->solid('gear'),
	'route' => 'profile',
];
$nav[] = [
	'name' => lang('Odhlásit'),
	'icon' => $fontawesome->solid('right-from-bracket'),
	'route' => 'logout',
];

return $nav;