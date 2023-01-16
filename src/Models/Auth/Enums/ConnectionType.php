<?php

namespace App\Models\Auth\Enums;

/**
 * @property string $value
 * @method static ConnectionType from(string $value)
 * @method static null|ConnectionType tryFrom(string $value)
 */
enum ConnectionType: string
{

	case RFID = 'rfid';
	case LASER_FORCE = 'laserforce';

	public function getReadable() : string {
		return match ($this) {
			self::RFID => 'RFID',
			self::LASER_FORCE => 'Laser force',
		};
	}

}