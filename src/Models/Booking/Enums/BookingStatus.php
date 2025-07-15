<?php
declare(strict_types=1);

namespace App\Models\Booking\Enums;

enum BookingStatus : string
{

	case ACTIVE = 'active';
	case COMPLETE = 'complete';

}
