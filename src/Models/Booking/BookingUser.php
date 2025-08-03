<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\Auth\PersonalDetails;
use App\Models\Auth\User;
use App\Models\BaseModel;
use App\Models\WithSoftDelete;
use Lsr\ObjectValidation\Attributes\Email;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
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

	public ?User $user = null;

	#[Email]
	public string $email;

	#[Instantiate]
	public PersonalDetails $personalDetails;

	public static function findByEmail(string $email): ?BookingUser {
		return self::query()
			->where('email', $email)
			->first();
	}

}