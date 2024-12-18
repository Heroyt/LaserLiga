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

$nav[] = [
	'name' => lang('Nastavení'),
	'icon' => $fontawesome->solid('gear'),
	'route' => 'profile',
];
$nav[] = [
	'name' => lang('Odhlásit'),
	'icon' => $fontawesome->solid('right-from-bracket'),
	'path' => ['logout'],
];

return $nav;