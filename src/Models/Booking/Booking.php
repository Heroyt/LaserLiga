<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\Arena;
use App\Models\BaseModel;
use App\Models\Booking\Enums\BookingStatus;
use App\Models\WithSoftDelete;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\ModelCollection;
use Lsr\Orm\ModelTraits\WithCreatedAt;
use Lsr\Orm\ModelTraits\WithUpdatedAt;

#[PrimaryKey('id_booking')]
class Booking extends BaseModel
{

	use WithSoftDelete;
	use WithCreatedAt;
	use WithUpdatedAt;

	public const string TABLE = 'bookings';

	#[ManyToOne]
	public Arena $arena;

	#[ManyToOne]
	public BookingType $type;

	#[ManyToOne]
	public ?BookingSubType $subtype = null;

	/** @var ModelCollection<BookingUser> */
	#[ManyToMany(through: 'booking_to_users', class: BookingUser::class)]
	public ModelCollection $users;

	public BookingStatus     $status = BookingStatus::ACTIVE;
	public DateTimeInterface $datetime;

	/** @var int<1,max> How many players are in this booking? */
	#[IntRange(min: 1)]
	public int     $playerCount   = 1;
	/** @var int<1, max> How many slots does this booking cover? */
	#[IntRange(min: 1)]
	public int     $slots         = 1;
	public bool    $locked        = false;
	public ?string $note          = null;
	public ?string $privateNote   = null;
	public ?string $subtypeFields = null;
	public ?string $terms         = null;

	#[ManyToOne]
	public ?Discovery $discovery = null;
	public ?string $customDiscovery = null;

	/** @var array<string,bool> */
	public array $filledSlots {
		get {
			if (empty($this->filledSlots)) {
				$length = $this->type->getLength();
				$date = new DateTime($this->datetime->format('Y-m-d H:i:s'));

				// Generate all slot times that this booking fills
				for ($i = 0; $i < $this->slots; $i++) {
					$this->filledSlots[$date->format('Y-m-d H:i')] = true;
					$date->add($length);
				}
			}
			return $this->filledSlots;
		}
	}

	public DateTimeImmutable $end {
		get {
			return $this->datetime->add($this->type->getLength($this->slots));
		}
	}

	/**
	 * @param DateTimeInterface $slot
	 *
	 * @return bool
	 */
	public function fillsSlot(DateTimeInterface $slot): bool {
		return isset($this->filledSlots[$slot->format('Y-m-d H:i')]);
	}



	/**
	 * @param string $format
	 * @return string[]
	 */
	public function getAllTimesFormatted(string $format = 'H:i'): array {
		$formatted = [];
		foreach ($this->getAllTimes() as $time) {
			$formatted[] = $time->format($format);
		}
		return $formatted;
	}

	/**
	 * @return DateTimeImmutable[]
	 */
	public function getAllTimes(): array {
		$start = $this->datetime;
		if ($start instanceof DateTime) {
			$start = DateTimeImmutable::createFromMutable($start);
		}
		$interval = $this->type->getLength($this->bookingSubtype->mergeSlots ?? 1);
		$slots = $this->slots / ($this->bookingSubtype->mergeSlots ?? 1);

		$times = [];
		for ($i = 0; $i < $slots; ++$i) {
			$times[] = $start;
			$start = $start->add($interval);
		}
		return $times;
	}

}