<?php

use App\Services\FontAwesomeManager;
use Lsr\Core\App;
use Lsr\Core\Session;

$fontawesome = App::getService('fontawesome');
assert($fontawesome instanceof FontAwesomeManager, 'Invalid service type from DI');

$session = App::getService('session');
assert($session instanceof Session, 'Invalid service type from DI');

$kioskArena = $session->get('kioskArena');

return [
	[
		'name' => lang('Hledat hráče'),
		'icon' => $fontawesome->solid('magnifying-glass'),
		'path' => ['kiosk', $kioskArena, 'search'],
	],
	[
		'name' => lang('Statistiky'),
		'icon' => $fontawesome->solid('eye'),
		'path' => ['kiosk', $kioskArena, 'stats'],
	],
	[
		'name' => lang('Hudební módy'),
		'icon' => $fontawesome->solid('music'),
		'path' => ['kiosk', $kioskArena, 'music'],
	],
	[
		'name' => lang('Hry'),
		'icon' => $fontawesome->solid('gun'),
		'path' => ['kiosk', $kioskArena, 'games'],
	],
	[
		'name' => lang('Žebříček'),
		'icon' => $fontawesome->solid('ranking-star'),
		'path' => ['kiosk', $kioskArena, 'leaderboard'],
	],
];