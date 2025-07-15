<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\Arena;
use App\Models\Auth\User;
use App\Models\BaseModel;
use App\Models\Booking\Enums\BookingStatus;
use App\Models\WithSoftDelete;
use DateTime;
use DateTimeImmutable;
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

	/** @var ModelCollection<User> */
	#[ManyToMany(through: 'booking_users', class: User::class)]
	public ModelCollection $users;

	public BookingStatus      $status = BookingStatus::ACTIVE;
	public DateTimeImmutable $datetime;

	public int     $playerCount   = 1;
	public int     $slots         = 1;
	public bool    $locked        = false;
	public ?string $note          = null;
	public ?string $privateNote   = null;
	public ?string $subtypeFields = null;
	public ?string $terms         = null;

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

}