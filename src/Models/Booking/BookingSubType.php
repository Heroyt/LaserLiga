<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\BaseModel;
use App\Models\Booking\DataObjects\BookingSubtypeFieldAssocRow;
use App\Models\WithIcon;
use App\Models\WithSoftDelete;
use Dibi\Exception;
use Lsr\Db\DB;
use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('id_subtype')]
class BookingSubType extends BaseModel
{
	use WithSoftDelete;
	use WithIcon;

	public const string TABLE = 'booking_subtypes';

	#[ManyToOne]
	public BookingType $type;

	/** @var ModelCollection<BookingSubtypeField> */
	public ModelCollection $fields {
		get {
			if (empty($this->fields)) {
				$this->fields = $this->findFields();
			}
			return $this->fields;
		}
	}

	public string  $name                = '';
	public ?string $description         = null;

	/** @var bool Fill the slot on booking regardless of player count */
	public bool    $slotFill            = false;
	public bool    $singlePlayerInput   = false;

	/** @var bool Allow booking on-call times as if it was normal open hour times. */
	public bool    $unlockOnCall        = false;

	/** @var int<1,max>|null Maximum count of players able to book this time (available vests) */
	#[IntRange(min: 1)]
	public ?int    $slotMax             = null;

	/** @var int<1,max>|null Minimum count of players able to book this time (available vests) */
	#[IntRange(min: 1)]
	public ?int    $slotMin             = null;

	/** @var int<1,max>|null On booking, merge available slots into larger. */
	#[IntRange(min: 1)]
	public ?int    $mergeSlots          = null;
	public ?string $datetimeDescription = null;
	public ?string $infoDescription     = null;
	public ?string $slotPreset          = null;

	/**
	 * @return ModelCollection<BookingSubtypeField>
	 * @throws Exception
	 * @throws ModelNotFoundException
	 */
	public function findFields(): ModelCollection {
		$rows = DB::select('booking_subtype_fields_assoc', 'id_field, order')
		          ->where('%n = %i', BookingSubtypeField::getPrimaryKey(), $this->id)
		          ->orderBy('`order`')
		          ->fetchIteratorDto(BookingSubtypeFieldAssocRow::class);
		$collection = new ModelCollection();
		foreach ($rows as $row) {
			$collection->add(BookingSubtypeField::get($row->id_field));
		}
		return $collection;
	}

}