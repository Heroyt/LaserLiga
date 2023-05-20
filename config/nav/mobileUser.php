<?php

use App\Models\Auth\User;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;


$nav = [];

/** @var User $user */
$user = App::getServiceByType(Auth::class)->getLoggedIn();

if (!empty($user->player?->getTournaments() ?? [])) {
	$nav[] = [
		'name' => lang('Moje turnaje'),
		'icon' => 'fa-solid fa-trophy',
		'route' => 'my-tournaments',
	];
}

$nav[] = [
	'name' => lang('Nastavení'),
	'icon' => 'fa-solid fa-gear',
	'route' => 'profile',
];
$nav[] = [
	'name' => lang('Odhlásit'),
	'icon' => 'fa-solid fa-right-from-bracket',
	'route' => 'logout',
];

return $nav;