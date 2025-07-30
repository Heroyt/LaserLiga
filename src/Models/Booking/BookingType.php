<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\Arena;
use App\Models\BaseModel;
use App\Models\WithIcon;
use App\Models\WithSoftDelete;
use DateInterval;
use DateMalformedIntervalStringException;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\ModelCollection;
use Lsr\Orm\ModelQuery;
use RuntimeException;

#[PrimaryKey('id_type')]
class BookingType extends BaseModel
{
	use WithSoftDelete;
	use WithIcon;

	public const string TABLE = 'booking_types';

	#[ManyToOne]
	public Arena $arena;

	public string $name       = '';
	public int    $slotLength = 30;
	public int    $slotLimit  = 11;

	public bool $openable = true;

	public ?string $calendarId  = null;
	public int     $openableMin = 0;

	#[OneToMany(class: BookingSubType::class)]
	public ModelCollection $subtypes;

	#[ManyToMany(through: 'booking_types_terms_and_conditions', class: TermAndCondition::class)]
	public ModelCollection $conditions;

	/** @var BookingSubType[] */
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

	public DateInterval $length {
		get {
			if (!isset($this->length)) {
				$this->length = new DateInterval('PT' . $this->slotLength . 'M');
			}
			return $this->length;
		}
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


}