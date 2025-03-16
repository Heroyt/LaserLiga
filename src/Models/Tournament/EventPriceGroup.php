<?php
declare(strict_types=1);

namespace App\Models\Tournament;

use App\Models\BaseModel;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\ModelCollection;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_event_price_group'), OA\Schema]
class EventPriceGroup extends BaseModel
{

	public const string TABLE = 'event_price_groups';

	#[OA\Property]
	public ?string $description = null;

	/** @var ModelCollection<EventPrice> */
	#[OneToMany(class: EventPrice::class), OA\Property]
	public ModelCollection $prices;

}