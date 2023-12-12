<?php

namespace App\Models\Tournament;

use OpenApi\Attributes as OA;

/**
 * @property string $value
 * @method static Requirement from(string $value)
 * @method static null|Requirement tryFrom(string $value)
 */
#[OA\Schema(type: 'string')]
enum Requirement: string
{

	case REQUIRED = 'required';
	case CAPTAIN = 'captain';
	case OPTIONAL = 'optional';
	case HIDDEN = 'hidden';

}
