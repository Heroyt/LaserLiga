<?php
declare(strict_types=1);

namespace App\Models\Tournament;

use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_event_price_group'), OA\Schema]
class EventPriceGroup extends Model
{

	public const string TABLE = 'event_price_groups';

	#[OA\Property]
	public ?string $description = null;

	/** @var EventPrice[] */
	#[OneToMany(class: EventPrice::class), OA\Property]
	public array $prices = [];

}