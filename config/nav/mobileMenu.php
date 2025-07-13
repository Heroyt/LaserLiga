<?php

use App\Services\FontAwesomeManager;
use Lsr\Core\App;

$fontawesome = App::getService('fontawesome');
assert($fontawesome instanceof FontAwesomeManager, 'Invalid service type from DI');

return [
	[
		'name'  => lang('Plánované akce'),
		'icon'  => $fontawesome->regular('calendar'),
		'route' => 'events',
	],
	[
		'name'  => lang('Žebříček'),
		'icon'  => $fontawesome->solid('ranking-star'),
		'route' => 'player-leaderboard',
	],
	[
		'name' => lang('Blog'),
		'route' => 'blog_index',
		'icon' => $fontawesome->solid('newspaper'),
	],
];