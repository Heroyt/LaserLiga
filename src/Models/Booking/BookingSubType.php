<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\BaseModel;
use App\Models\Booking\DataObjects\BookingSubtypeFieldAssocRow;
use App\Models\Booking\Translations\BookingSubTypeTranslation;
use App\Models\WithIcon;
use App\Models\WithSoftDelete;
use Dibi\Exception;
use Lsr\Db\DB;
use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\ModelCollection;

/**
 * @implements TranslatableModel<BookingSubTypeTranslation>
 * @use Translatable<BookingSubTypeTranslation>
 */
#[PrimaryKey('id_subtype')]
class BookingSubType extends BaseModel implements TranslatableModel
{
	use WithSoftDelete;
	use WithIcon;
	use Translatable;

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

	/** @var ModelCollection<BookingSubtypeField> */
	#[NoDB]
	public ModelCollection $publicFields {
		get => $this->fields->filter(fn(BookingSubtypeField $field) => !$field->private);
	}

	public string  $name        = '';
	public ?string $description = null;

	/** @var bool Fill the slot on booking regardless of player count */
	public bool $slotFill          = false;
	public bool $singlePlayerInput = false;

	/** @var bool Allow booking on-call times as if it was normal open hour times. */
	public bool $unlockOnCall = false;

	/** @var int<1,max>|null Maximum count of players able to book this time (available vests) */
	#[IntRange(min: 0)]
	public ?int $slotMax = null;

	/** @var int<1,max>|null Minimum count of players able to book this time (available vests) */
	#[IntRange(min: 0)]
	public ?int $slotMin = null;

	/** @var int<1,max>|null On booking, merge available slots into larger. */
	public ?int    $mergeSlots          = null;
	public ?string $datetimeDescription = null;
	public ?string $infoDescription     = null;
	public ?string $slotPreset          = null;

	#[NoDB]
	public string $translationClass {
		get => BookingSubTypeTranslation::class;
	}

	/**
	 * @return ModelCollection<BookingSubtypeField>
	 * @throws Exception
	 * @throws ModelNotFoundException
	 */
	public function findFields(): ModelCollection {
		$rows = DB::select('booking_subtype_fields_assoc', '[id_field], [order]')
		          ->where('%n = %i', BookingSubtype::getPrimaryKey(), $this->id)
		          ->orderBy('[order]')
		          ->cacheTags($this::TABLE, BookingSubtypeField::TABLE)
		          ->fetchIteratorDto(BookingSubtypeFieldAssocRow::class);
		$collection = new ModelCollection();
		foreach ($rows as $row) {
			$collection->push(BookingSubtypeField::get($row->id_field));
		}
		return $collection;
	}

	public function getTranslatedName(?string $language = null): string {
		return $this->getTranslation($language)->name;
	}

	public function getTranslatedDescription(?string $language = null): ?string {
		return $this->getTranslation($language)->description;
	}

	public function getTranslatedDatetimeDescription(?string $language = null): ?string {
		return $this->getTranslation($language)->datetimeDescription;
	}

	public function getTranslatedInfoDescription(?string $language = null): ?string {
		return $this->getTranslation($language)->infoDescription;
	}

}