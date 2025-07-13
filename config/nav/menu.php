<?php

use App\Services\FontAwesomeManager;
use Lsr\Core\App;

$fontawesome = App::getService('fontawesome');
assert($fontawesome instanceof FontAwesomeManager, 'Invalid service type from DI');

return [
	[
		'name' => lang('Arény'),
		'route' => 'arenas-list',
		'icon' => $fontawesome->solid('location-dot'),
	],
	[
		'name'  => lang('Plánované akce'),
		'route' => 'events',
		'icon'  => $fontawesome->regular('calendar'),
	],
	[
		'name' => lang('Žebříček'),
		'route' => 'player-leaderboard',
		'icon' => $fontawesome->solid('ranking-star'),
	],
	[
		'name' => lang('Blog'),
		'route' => 'blog_index',
		'icon' => $fontawesome->solid('newspaper'),
	],
];