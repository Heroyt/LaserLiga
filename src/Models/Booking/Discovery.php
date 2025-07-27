<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\Arena;
use App\Models\BaseModel;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_discovery')]
class Discovery extends BaseModel
{

	public const string TABLE = 'booking_discovery';

	#[ManyToOne]
	public Arena $arena;

	public string $name = '';

}