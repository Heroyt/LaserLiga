<?php

namespace App\Models\DataObjects\Highlights;

use OpenApi\Attributes as OA;

#[OA\Schema(type: 'string')]
enum GameHighlightType: string
{

	case TROPHY       = 'trophy';
	case OTHER        = 'other';
	case ALONE_STATS  = 'alone';
	case HITS         = 'hits';
	case USER_AVERAGE = 'user_average';

}
