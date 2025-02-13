<?php
declare(strict_types=1);

namespace App\Models\Tournament;

use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_event_price')]
class EventPrice extends Model
{

	public const string TABLE = 'event_prices';

	#[ManyToOne]
	public EventPriceGroup $eventPriceGroup;

	public string $description;
	public float $price;
}