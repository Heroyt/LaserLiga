<?php
declare(strict_types=1);

namespace App\Request\Booking;

use App\Core\ObjectValidators\ModelId;
use App\Core\ObjectValidators\ValidateEach;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\BookingType;
use App\Models\Booking\Discovery;
use App\Models\Booking\Enums\FieldType;
use App\Services\Booking\BookingCalendarProvider;
use DateTimeImmutable;
use Lsr\Core\App;
use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\ObjectValidation\Exceptions\ValidationMultiException;

class NewBookingRequest
{

	protected const bool VALIDATE_TIMES = true;

	#[Required('Typ rezervace je povinný'), ModelId(BookingType::class, 'Neplatný typ rezervace')]
	public int $type;

	#[ModelId(BookingSubType::class)]
	public ?int $subtype = null;

	#[Required('Vyberte datum rezervace')]
	public DateTimeImmutable $date;

	/** @var non-empty-array<string> */
	#[Required('Vyberte alespoň jeden čas')]
	public array $time;

	/** @var non-empty-array<string,int<1,max>> */
	public ?array $players = null;

	/** @var int<1,max>|null  */
	#[IntRange(min: 1, message: 'Zadejte platný počet hráčů')]
	public ?int $playerCount = null;

	/** @var non-empty-array<NewBookingUserRequest> */
	#[Required, ValidateEach]
	public array $users;

	public ?string $note = null;

	public bool $unlocked = false;

	/** @var array<string, mixed> */
	public array $sub = [];

	#[ModelId(Discovery::class)]
	public ?int $discovery = null;

	public function addUser(NewBookingUserRequest $user): void {
		$this->users[] = $user;
	}

	public function addTime(string $time): void {
		$this->time[] = $time;
	}

	/**
	 * @throws ValidationException
	 */
	public function validate(): void {
		/** @var ValidationException[] $exceptions */
		$exceptions = [];

		$type = BookingType::get($this->type);
		$subtype = $this->subtype > 0 ? BookingSubType::get($this->subtype) : null;

		$bookingCalendarProvider = App::getServiceByType(BookingCalendarProvider::class);
		assert($bookingCalendarProvider instanceof BookingCalendarProvider);

		// Validate times
		foreach ($this->time as $time) {
			if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
				$exceptions[] = ValidationException::createWithCustomMessage(
					$this,
					'time',
					'Neplatný čas rezervace',
					$this->time,
				);
				break;
			}

			if (!isset($this->players[$time]) && $this->playerCount === null) {
				$exceptions[] = ValidationException::createWithCustomMessage(
					$this,
					'players-' . $time,
					'Zadejte počet hráčů pro všechny časy',
					$this->players,
				);
				break;
			}

			/** @var string|int $playerCount */
			$playerCount = $this->players[$time] ?? $this->playerCount;
			if (!is_numeric($playerCount) || ((int)$playerCount) < 1 || ((int)$playerCount) > 100) {
				$exceptions[] = ValidationException::createWithCustomMessage(
					$this,
					'players-' . $time,
					'Zadejte platný počet hráčů',
					$this->players,
				);
			}
			$this->players[$time] = (int)$playerCount;

			if ($this::VALIDATE_TIMES) {
				// Validate if slot is available
				$datetime = (clone $this->date)->setTime((int)substr($time, 0, 2), (int)substr($time, 3, 2));
				// Convert datetime to valid slot time
				$datetime = $bookingCalendarProvider->getSlotTime($datetime, $type, $subtype);
				if (!$bookingCalendarProvider->isSlotAvailable($datetime, $type, $subtype, (int) $playerCount)) {
					$exceptions[] = ValidationException::createWithCustomMessage(
						$this,
						'time',
						'Vybraný čas není dostupný',
						$time,
					);
				}
			}
		}

		// Validate subfields
		if ($this->subtype > 0) {
			assert($subtype !== null);
			foreach ($subtype->fields as $field) {
				if ($field->required && empty($this->sub[$field->getName()])) {
					$exceptions[] = ValidationException::createWithCustomMessage(
						$this,
						'sub.' . $field->getName(),
						'Pole je povinné',
						'',
					);
					continue;
				}

				// Validate values
				switch ($field->type) {
					case FieldType::MULTI:
					case FieldType::SELECT:
						$value = $this->sub[$field->getName()];
						$validValues = array_map(static fn($v) => $v->value, $field->parsedValues);
						$values = is_array($value) ? $value : [$value];
						if (array_any($values, static fn($v) => !in_array($v, $validValues, true))) {
							$exceptions[] = ValidationException::createWithCustomMessage(
								$this,
								'sub.' . $field->getName(),
								'Neplatná hodnota',
								$value,
							);
						}
						break;
					case FieldType::NUMBER:
						$value = $this->sub[$field->getName()];
						if (!is_numeric($value)) {
							$exceptions[] = ValidationException::createWithCustomMessage(
								$this,
								'sub.' . $field->getName(),
								'Zadejte platné číslo',
								$value,
							);
							break;
						}
						$this->sub[$field->getName()] = (int)$value;
						break;
					case FieldType::BOOL:
						$value = $this->sub[$field->getName()] ?? false;
						$this->sub[$field->getName()] = (bool)$value;
						break;
				}
			}
		}

		if (!empty($exceptions)) {
			throw new ValidationMultiException($exceptions);
		}
	}


}