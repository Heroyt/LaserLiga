<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\Arena;
use App\Models\BaseModel;
use App\Models\Booking\Translations\BookingTypeTranslation;
use App\Models\WithIcon;
use App\Models\WithSoftDelete;
use DateInterval;
use DateMalformedIntervalStringException;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\ModelCollection;
use Lsr\Orm\ModelQuery;
use RuntimeException;

/**
 * @implements TranslatableModel<BookingTypeTranslation>
 * @use Translatable<BookingTypeTranslation>
 */
#[PrimaryKey('id_type')]
class BookingType extends BaseModel implements TranslatableModel
{
	use WithSoftDelete;
	use WithIcon;
	use Translatable;

	public const string TABLE = 'booking_types';

	#[ManyToOne]
	public Arena $arena;

	public string $name       = '';
	/** @var int<1,max> */
	public int    $slotLength = 30;
	/** @var int<1,max> */
	public int    $slotLimit  = 11;

	/** @var bool Can set booking to allow more bookings to book on the same time. */
	public bool $openable = true;

	/** @var int Minimum amount of people to allow locking the booking for only one group. */
	public int     $openableMin = 0;

	/** @var string|null Google calendar ID */
	public ?string $calendarId  = null;

	/** @var ModelCollection<BookingSubType> */
	#[OneToMany(class: BookingSubType::class)]
	public ModelCollection $subtypes;

	/** @var ModelCollection<TermAndCondition>  */
	#[ManyToMany(through: 'booking_types_terms_and_conditions', class: TermAndCondition::class)]
	public ModelCollection $conditions;

	/** @var BookingSubType[] */
	#[NoDB]
	public array $activeSubtypes = [] {
		get {
			if (empty($this->activeSubtypes)) {
				/** @var BookingSubType $subtype */
				foreach ($this->subtypes as $subtype) {
					if ($subtype->deleted) {
						continue;
					}
					$this->activeSubtypes[] = $subtype;
				}
			}
			return $this->activeSubtypes;
		}
	}

	#[NoDB]
	public DateInterval $length {
		get {
			if (!isset($this->length)) {
				$this->length = new DateInterval('PT' . $this->slotLength . 'M');
			}
			return $this->length;
		}
	}

	#[NoDB]
	public string $translationClass {
		get => BookingTypeTranslation::class;
	}

	/**
	 * @param Arena $arena
	 *
	 * @return BookingType[]
	 */
	public static function getAllForArena(Arena $arena): array {
		return self::queryForArena($arena)->get();
	}

	public static function queryForArena(Arena $arena): ModelQuery {
		return self::queryActive()->where('[id_arena] = %i', $arena->id);
	}

	/**
	 * @param positive-int $multiplier
	 *
	 * @return DateInterval
	 */
	public function getLength(int $multiplier = 1): DateInterval {
		if ($multiplier > 1) {
			try {
				return new DateInterval('PT' . ($this->slotLength * $multiplier) . 'M');
			} catch (DateMalformedIntervalStringException $e) {
				throw new RuntimeException('Invalid interval string for booking type length: ' . $e->getMessage(), 0, $e);
			}
		}
		return $this->length;
	}

	public function getTranslatedName(?string $language = null): string {
		return $this->getTranslation($language)->name;
	}
}