<?php

namespace App\Models\Auth\Enums;

enum ConnectionType: string
{

	case RFID = 'rfid';
	case LASER_FORCE = 'laserforce';

}