<?php
declare(strict_types=1);

namespace App\Models\Tournament;

use App\Models\BaseModel;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_event_price')]
class EventPrice extends BaseModel
{

	public const string TABLE = 'event_prices';

	#[ManyToOne]
	public EventPriceGroup $eventPriceGroup;

	public string $description;
	public float  $price;
}