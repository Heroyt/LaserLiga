<?php

namespace App\Helpers;

/**
 * @property string $value
 */
enum Gender: string
{

	case MALE   = 'm';
	case FEMALE = 'f';
	case OTHER  = 'o';

}
