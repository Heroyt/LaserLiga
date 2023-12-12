<?php

namespace App\Models\Tournament;

use OpenApi\Attributes as OA;

#[OA\Schema(type: 'string')]
enum RegistrationType: string
{
	case LEAGUE     = 'league';
	case TOURNAMENT = 'tournament';
	case BOTH       = 'both';
}
