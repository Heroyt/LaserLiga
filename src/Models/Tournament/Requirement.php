<?php

namespace App\Models\Tournament;

/**
 * @property string $value
 * @method static Requirement from(string $value)
 * @method static null|Requirement tryFrom(string $value)
 */
enum Requirement: string
{

	case REQUIRED = 'required';
	case CAPTAIN = 'captain';
	case OPTIONAL = 'optional';
	case HIDDEN = 'hidden';

}
