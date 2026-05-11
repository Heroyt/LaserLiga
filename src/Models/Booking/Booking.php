<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\Arena;
use App\Models\BaseModel;
use App\Models\Booking\Enums\BookingStatus;
use App\Models\WithSoftDelete;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToMany;
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
	public DateTimeImmutable $datetime;

	/** @var ModelCollection<BookingSlot> */
	#[OneToMany(class: BookingSlot::class)]
	public ModelCollection $slots;

	public bool    $locked        = false;
	public ?string $note          = null;
	public ?string $privateNote   = null;
	public ?string $subtypeFields = null;
	public ?string $terms         = null;

	#[ManyToOne]
	public ?Discovery $discovery       = null;
	public ?string    $customDiscovery = null;

	public ?string $eventId = null;

	/** @var array<string,int> */
	#[NoDB]
	public array $filledSlots {
		get {
			if (empty($this->filledSlots)) {
				$this->filledSlots = [];
				foreach ($this->slots as $slot) {
					foreach ($slot->allTimes as $time) {
						$this->filledSlots[$time->format('Y-m-d H:i')] = $slot->playerCount;
					}
				}
			}
			return $this->filledSlots;
		}
	}

	/**
	 * @var array<string, mixed>|null
	 */
	#[NoDB]
	public ?array $subtypeFieldsParsed {
		get {
			if (!isset($this->subtypeFieldsParsed)) {
				if (empty($this->subtypeFields)) {
					return null;
				}
				$this->subtypeFieldsParsed = json_decode($this->subtypeFields, true, 512, JSON_THROW_ON_ERROR);
			}
			return $this->subtypeFieldsParsed;
		}
		set (?array $value) {
			$this->subtypeFieldsParsed = $value;
			$this->subtypeFields = $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
		}
	}

	#[NoDB]
	public string $title {
		get => $this->type->getTranslatedName() . ' - ' . $this->datetime->format(
				'j. n. Y'
			) . ' - ' . $this->user->personalDetails->firstName . ' ' . $this->user->personalDetails->lastName;
	}

	#[NoDB]
	public BookingUser $user {
		get => $this->users->first();
	}

	#[NoDB]
	public int $playerCount {
		get => $this->slots->first()?->playerCount ?? 0;
	}

	/**
	 * @param DateTimeInterface $slot
	 *
	 * @return bool
	 */
	public function fillsSlot(DateTimeInterface $slot): bool {
		$datetime = $slot->format('Y-m-d H:i');
		return isset($this->filledSlots[$datetime]) && $this->filledSlots[$datetime] > 0;
	}

	/**
	 * @param string $format
	 *
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
		$times = [];
		foreach ($this->slots as $slot) {
			foreach ($slot->allTimes as $date) {
				$times[] = $date;
			}
		}
		return $times;
	}

	public function getSubTypeField(string $name): mixed {
		return $this->subtypeFieldsParsed[$name] ?? null;
	}

	public function setSubTypeField(string $name, mixed $value): void {
		$fields = $this->subtypeFieldsParsed ?? []; // Ensure we have the parsed fields available
		$fields[$name] = $value;                    // Update the field with the new value
		$this->subtypeFieldsParsed = $fields;       // Save the updated fields back to the model
	}

	public function jsonSerialize(): array {
		$data = parent::jsonSerialize();
		if (isset($this->bookingSubtype)) {
			$data['subtypeFields'] = $this->subtypeFieldsParsed;
		}
		return $data;
	}

	public function addSlots(BookingSlot ...$slots): void {
		foreach ($slots as $slot) {
			$slot->booking = $this;
			$this->slots->push($slot);
		}
	}

	public function saveBookingSlots(): bool {
		foreach ($this->slots as $slot) {
			if (!$slot->save()) {
				return false;
			}
		}
		return true;
	}

	public function getCacheTags(): array {
		$tags = parent::getCacheTags();
		if (isset($this->type, $this->datetime)) {
			$tags[] = 'booking/times/' . $this->type->id . '/' . $this->datetime->format('Y-m-d');
			$tags[] = 'booking/bookings/' . $this->type->id . '/' . $this->datetime->format('Y-m-d');
		}
		return $tags;
	}

	public function isPlayerCountSame(): bool {
		$count = null;
		foreach ($this->slots as $slot) {
			$count ??= $slot->playerCount;
			if ($slot->playerCount !== $count) {
				return false;
			}
		}
		return true;
	}

	public function getSlot(string $time) : ?BookingSlot {
		return $this->slots->first(fn(BookingSlot $slot) => $slot->time->format('H:i') === $time) ?: null;
	}
}