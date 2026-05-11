<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\Auth\PersonalDetails;
use App\Models\Auth\User;
use App\Models\BaseModel;
use App\Models\WithSoftDelete;
use Lsr\ObjectValidation\Attributes\Email;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\ModelCollection;
use Lsr\Orm\ModelTraits\WithCreatedAt;
use Lsr\Orm\ModelTraits\WithUpdatedAt;

#[PrimaryKey('id_booking_user')]
class BookingUser extends BaseModel
{
	use WithCreatedAt;
	use WithUpdatedAt;
	use WithSoftDelete;

	public const string TABLE = 'booking_users';

	/** @var ModelCollection<Booking>  */
	#[ManyToMany(through: 'booking_to_users', class: Booking::class)]
	public ModelCollection $bookings;

	#[ManyToOne()]
	public ?User $user = null;

	#[Email]
	public string $email;

	#[Instantiate]
	public PersonalDetails $personalDetails;

	#[NoDB]
	public bool $anonymous {
		get => empty($this->email);
	}

	public static function findByEmail(string $email, bool $cache = true): ?BookingUser {
		if (empty($email)) {
			return null; // Anonymous user
		}
		return self::query()
			->where('email = %s', $email)
			->first($cache);
	}

	/**
	 * @return BookingUser[]
	 */
	public static function findAllByEmail(string $email, bool $cache = true): array {
		if (empty($email)) {
			return []; // Anonymous user
		}
		return self::query()
			->where('email = %s', $email)
			->get($cache);
	}

}