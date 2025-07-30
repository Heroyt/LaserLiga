<?php
declare(strict_types=1);

namespace App\Models\Booking\Enums;

enum TimeStatus : string
{

	case AVAILABLE = 'AVAILABLE';
	case FILLED = 'FILLED';
	case ON_CALL = 'ON_CALL';
	case PARTIALLY_FILLED = 'PARTIALLY_FILLED';
	case CLOSED = 'CLOSED';

	public function isFinalStatus(): bool
	{
		return match ($this) {
			self::AVAILABLE, self::ON_CALL, self::PARTIALLY_FILLED => false,
			self::FILLED, self::CLOSED => true,
		};
	}

}
