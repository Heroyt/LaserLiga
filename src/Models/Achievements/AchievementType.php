<?php

namespace App\Models\Achievements;

use OpenApi\Attributes as OA;

#[OA\Schema(type: 'string')]
enum AchievementType: string
{

	case GAME_COUNT           = 'game_count';
	case GAMES_PER_DAY        = 'games_per_day';
	case ACCURACY             = 'accuracy';
	case ARENAS               = 'arenas';
	case POSITION             = 'position';
	case HITS                 = 'hits';
	case DEATHS               = 'deaths';
	case KD                   = 'k:d';
	case SHOTS_MIN            = 'shots_min';
	case SHOTS_MAX            = 'shots_max';
	case GAME_DAYS_SUCCESSIVE = 'game_days_successive';
	case GAMES_PER_MONTH      = 'games_per_month';
	case SIGNUP               = 'signup';
	case TOURNAMENT_PLAY      = 'tournament_play';
	case TOURNAMENT_POSITION  = 'tournament_position';
	case LEAGUE_POSITION      = 'league_position';
	case BONUS                = 'bonus';
	case BONUS_SHIELD         = 'bonus_shield';
	case BONUS_MACHINE_GUN    = 'bonus_machine_gun';
	case BONUS_INVISIBILITY   = 'bonus_invisibility';
	case BONUS_SPY            = 'bonus_spy';
	case TROPHY               = 'trophy';
	case BIRTHDAY               = 'birthday';

}
