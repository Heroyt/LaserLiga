<?php
declare(strict_types=1);

namespace App\Models\Booking\Enums;

enum FieldType : string
{

	case TEXT = 'text';
	case BOOL = 'bool';
	case SELECT = 'select';
	case MULTI = 'multi';
	case NUMBER = 'number';

}
