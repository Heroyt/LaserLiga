<?php
declare(strict_types=1);

namespace App\Templates\Kiosk;

enum DashboardType : string
{
	case DASHBOARD = 'dashboard';
	case GAMES = 'games';
	case STATS = 'stats';
	case MUSIC = 'music';
	case LEADERBOARD = 'leaderboard';
	case SEARCH = 'search';
}
