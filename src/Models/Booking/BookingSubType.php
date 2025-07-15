<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\BaseModel;
use App\Models\Booking\DataObjects\BookingSubtypeFieldAssocRow;
use App\Models\WithIcon;
use App\Models\WithSoftDelete;
use Dibi\Exception;
use Lsr\Db\DB;
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
	public bool    $slotFill            = false;
	public bool    $singlePlayerInput   = false;
	public bool    $unlockOnCall        = false;
	public ?int    $slotMax             = null;
	public ?int    $slotMin             = null;
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