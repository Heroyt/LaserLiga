<?php

use App\Services\FontAwesomeManager;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;

/** @var Auth $auth */
$auth = App::getServiceByType(Auth::class);

$fontawesome = App::getService('fontawesome');
assert($fontawesome instanceof FontAwesomeManager, 'Invalid service type from DI');

$session = App::getService('session');
assert($session instanceof \Lsr\Core\Session, 'Invalid service type from DI');

$kioskArena = $session->get('kioskArena');

$nav = [
	[
		'name' => lang('Úvod'),
		'icon' => $fontawesome->solid('house'),
		'path' => ['kiosk', (string) $kioskArena],
	],
];

return $nav;