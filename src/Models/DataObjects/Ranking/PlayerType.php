<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Ranking;

enum PlayerType : string
{
	case ENEMY = 'enemy';
	case TEAMMATE = 'teammate';
}
