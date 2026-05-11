<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\BaseModel;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_booking_slot')]
class BookingSlot extends BaseModel
{

	public const string TABLE = 'booking_slots';

	#[ManyToOne]
	public Booking           $booking;
	public DateTimeInterface $time;

	/** @var int<1,max> How many time slots does this booking span? */
	#[IntRange(min: 1)]
	public int $span = 1;

	/** @var int<1,max> How many players are in this booking? */
	#[IntRange(min: 1)]
	public int $playerCount = 1;

	public ?string $eventId = null;

	#[NoDB]
	public DateTimeImmutable $datetime {
		get {
			if (!isset($this->datetime)) {
				$this->datetime = DateTimeImmutable::createFromFormat(
					'Y-m-d H:i',
					$this->booking->datetime->format('Y-m-d') . ' ' . $this->time->format('H:i')
				);
			}
			return $this->datetime;
		}
	}

	#[NoDB]
	public DateTimeImmutable $end {
		get {
			if (!isset($this->end)) {
				$this->end = $this->datetime->add($this->booking->type->getLength($this->span));
			}
			return $this->end;
		}
	}

	#[NoDB]
	public string $summary {
		get {
			/** @var BookingUser|null $mainUser */
			$mainUser = $this->booking->users->first();
			if ($mainUser === null) {
				throw new \RuntimeException('Booking must have at least one user.');
			}
			return $mainUser->personalDetails->firstName . ' ' . $mainUser->personalDetails->lastName
				. ' (' . $mainUser->personalDetails->phone . ') - '
				. lang('%d hráč', '%d hráčů', $this->playerCount, format: [$this->playerCount]);
		}
	}

	#[NoDB]
	public string $description {
		get {
			$description = 'Rezervace ' . $this->booking->type->name . "\n";
			if ($this->booking->subtype !== null) {
				$description .= 'Typ: ' . $this->booking->subtype->name . "\n";
			}
			$description .= 'Hráčů: ' . $this->playerCount . "\n";

			foreach ($this->booking->users as $user) {
				$description .= 'Hráč: ' . $user->personalDetails->firstName . ' ' . $user->personalDetails->lastName . "\n";
				$description .= 'Telefon: ' . $user->personalDetails->phone . "\n";
				$description .= 'E-mail: ' . $user->email . "\n";
				$description .= "----------------------------------\n";
			}

			if ($this->booking->subtype !== null) {
				foreach ($this->booking->subtype->fields as $field) {
					$value = $this->booking->getSubTypeField($field->name);
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
			if (!empty($this->booking->note)) {
				$description .= "\nPoznámka:\n" . $this->booking->note . "\n";
			}
			if (!empty($this->booking->privateNote)) {
				$description .= "\nPoznámka obsluhy:\n" . $this->booking->privateNote . "\n";
			}
			return $description;
		}
	}

	/** @var DateTimeImmutable[] */
	#[NoDB]
	public array $allTimes {
		get {
			if (!isset($this->allTimes)) {
				$start = $this->datetime;
				$slotLength = $this->booking->type->getLength();
				$this->allTimes = [];
				for ($i = 0; $i < $this->span; $i++) {
					$this->allTimes[] = $start;
					$start = $start->add($slotLength);
				}
			}
			return $this->allTimes;
		}
	}

	/**
	 * @return string[]
	 */
	public function getAllTimesFormatted(string $format = 'H:i'): array {
		$times = [];
		foreach ($this->allTimes as $time) {
			$times[] = $time->format($format);
		}
		return $times;
	}

	public function getCacheTags(): array {
		$tags = parent::getCacheTags();
		if (isset($this->booking)) {
			$tags[] = Booking::TABLE . '/' . $this->booking->id;
			$tags[] = Booking::TABLE . '/' . $this->booking->id . '/relations';
			$tags[] = 'booking/times/' . $this->booking->type->id . '/' . $this->datetime->format('Y-m-d');
			$tags[] = 'booking/bookings/' . $this->booking->type->id . '/' . $this->datetime->format('Y-m-d');
		}
		return $tags;
	}
}