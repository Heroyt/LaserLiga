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
	public DateTimeImmutable $datetime;

	/** @var int<1,max> How many players are in this booking? */
	#[IntRange(min: 1)]
	public int $playerCount = 1;
	/** @var int<1, max> How many slots does this booking cover? */
	#[IntRange(min: 1)]
	public int     $slots         = 1;
	public bool    $locked        = false;
	public ?string $note          = null;
	public ?string $privateNote   = null;
	public ?string $subtypeFields = null;
	public ?string $terms         = null;

	#[ManyToOne]
	public ?Discovery $discovery       = null;
	public ?string    $customDiscovery = null;

	public ?string $eventId = null;

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

	public string $summary {
		get {
			/** @var BookingUser|null $mainUser */
			$mainUser = $this->users->first();
			if ($mainUser === null) {
				throw new \RuntimeException('Booking must have at least one user.');
			}
			return $mainUser->personalDetails->firstName . ' ' . $mainUser->personalDetails->lastName
				. ' (' . $mainUser->personalDetails->phone . ') - '
				. lang('%d hráč', '%d hráčů', $this->playerCount, format: [$this->playerCount]);
		}
	}

	public string $description {
		get {
			$description = 'Rezervace ' . $this->type->name . "\n";
			if (isset($this->bookingSubtype)) {
				$description .= 'Typ: ' . $this->bookingSubtype->name . "\n";
			}
			$description .= 'Hráčů: ' . $this->playerCount . "\n";

			foreach ($this->users as $user) {
				$description .= 'Hráč: ' . $user->personalDetails->firstName . ' ' . $user->personalDetails->lastName . "\n";
				$description .= 'Telefon: ' . $user->personalDetails->phone . "\n";
				$description .= 'E-mail: ' . $user->email . "\n";
				$description .= "----------------------------------\n";
			}

			if (isset($this->bookingSubtype)) {
				foreach ($this->bookingSubtype->getFields() as $field) {
					$value = $this->getSubTypeField($field->getName());
					if (empty($value) && $value !== false) {
						continue;
					}
					$description .= $field->label . ': ' . match ($field->type) {
							Enums\FieldType::BOOL   => $value ? 'Ano' : 'Ne',
							Enums\FieldType::SELECT => $field->getLabelForValue($value),
							Enums\FieldType::MULTI  => implode(', ', $field->getLabelsForValues(...$value)),
							default                 => $value,
						} . "\n";
				}
			}
			if (!empty($this->note)) {
				$description .= "\nPoznámka:\n" . $this->note . "\n";
			}
			if (!empty($this->privateNote)) {
				$description .= "\nPoznámka obsluhy:\n" . $this->privateNote . "\n";
			}
			return $description;
		}
	}

	/**
	 * @var array<string, mixed>|null
	 */
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
		$start = $this->datetime;
		$interval = $this->type->getLength($this->bookingSubtype->mergeSlots ?? 1);
		$slots = $this->slots / ($this->bookingSubtype->mergeSlots ?? 1);

		$times = [];
		for ($i = 0; $i < $slots; ++$i) {
			$times[] = $start;
			$start = $start->add($interval);
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

}