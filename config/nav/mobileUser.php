<?php

use App\Models\Auth\User;
use App\Services\FontAwesomeManager;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;


$nav = [];

$auth = App::getService('auth');
assert($auth instanceof Auth, 'Invalid service type from DI');
/** @var User $user */
$user = $auth->getLoggedIn();

$fontawesome = App::getService('fontawesome');
assert($fontawesome instanceof FontAwesomeManager, 'Invalid service type from DI');

if (!empty($user->player?->getTournaments() ?? [])) {
	$nav[] = [
		'name'  => lang('Moje turnaje'),
		'icon'  => $fontawesome->solid('trophy'),
		'route' => 'my-tournaments',
	];
}

if (($user->hasRight('view-arena') || $user->hasRight('manage-arena') || $user->hasRight('manage-arenas'))) {
	$nav[] = [
		'name'  => lang('Správa arény'),
		'route' => 'admin-arenas',
		'icon'  => $fontawesome->solid('house-lock'),
	];
}

if ($user->type->superAdmin) {
	$nav[] = [
		'name'  => 'Tracy off',
		'route' => 'tracy-off',
		'icon'  => $fontawesome->solid('bug'),
	];
	$nav[] = [
		'name'  => 'Tracy on',
		'route' => 'tracy-on',
		'icon'  => $fontawesome->solid('bug'),
	];
}

$nav[] = [
	'name'  => lang('Nastavení'),
	'icon'  => $fontawesome->solid('gear'),
	'route' => 'profile',
];
$nav[] = [
	'name'  => lang('Odhlásit'),
	'icon'  => $fontawesome->solid('right-from-bracket'),
	'path' => ['logout'],
];

return $nav;