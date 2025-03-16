<?php

namespace App\Models\Auth\Enums;

use Exception;

/**
 * @property string $value
 * @method static ConnectionType from(string $value)
 * @method static null|ConnectionType tryFrom(string $value)
 */
enum ConnectionType: string
{

	case RFID         = 'rfid';
	case LASER_FORCE  = 'laserforce';
	case MY_LASERMAXX = 'mylasermaxx';
	case OTHER        = 'other';

	public function getReadable(): string {
		return match ($this) {
			self::RFID         => 'RFID',
			self::LASER_FORCE  => 'Laser force',
			self::MY_LASERMAXX => 'My LaserMaxx',
			self::OTHER        => lang('Other'),
		};
	}

	public function getIcon(): string {
		return match ($this) {
			self::RFID         => throw new Exception('To be implemented'),
			self::LASER_FORCE  => 'laserforce',
			self::MY_LASERMAXX => 'lasermaxx',
			self::OTHER        => throw new Exception('To be implemented'),

		};
	}

	public function getColor(): string {
		return match ($this) {
			self::LASER_FORCE       => 'secondary',
			self::MY_LASERMAXX      => 'dark',
			self::RFID, self::OTHER => 'primary',
		};
	}

	public function getName(): string {
		return match ($this) {
			self::RFID         => 'RFID',
			self::LASER_FORCE  => 'LaserForce',
			self::MY_LASERMAXX => 'My LaserMaxx',
			self::OTHER        => lang('Ostatní'),
		};
	}

}