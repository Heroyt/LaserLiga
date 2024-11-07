<?php

use App\Models\Auth\User;
use App\Services\FontAwesomeManager;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;

$fontawesome = App::getService('fontawesome');
assert($fontawesome instanceof FontAwesomeManager, 'Invalid service type from DI');

$nav = [
	[
		'name' => lang('Moje hry'),
		'route' => 'my-game-history',
		'icon' => $fontawesome->solid('gun'),
	],
];

/** @var User $user */
$user = App::getServiceByType(Auth::class)->getLoggedIn();

if (!empty($user->player?->getTournaments() ?? [])) {
	$nav[] = [
		'name' => lang('Moje turnaje'),
		'route' => 'my-tournaments',
		'icon' => $fontawesome->solid('trophy'),
	];
}

return $nav;