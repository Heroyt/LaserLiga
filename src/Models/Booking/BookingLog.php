<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\Auth\User;
use App\Models\BaseModel;
use App\Models\Booking\Enums\LogAction;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\ModelQuery;
use Lsr\Orm\ModelTraits\WithCreatedAt;

#[PrimaryKey('id_booking_log')]
class BookingLog extends BaseModel
{
	use WithCreatedAt;

	public const string TABLE = 'booking_logs';

	#[ManyToOne]
	public Booking $booking;
	public LogAction $action;
	public ?string $changes = null;
	#[ManyToOne]
	public ?User $user = null;

	/**
	 * @return BookingLog[]
	 */
	public static function getForBooking(Booking $booking) : array {
		return self::queryForBooking($booking)->get();
	}

	/**
	 * @return ModelQuery<BookingLog>
	 */
	public static function queryForBooking(Booking $booking) : ModelQuery {
		return self::query()->where('id_booking = %i', $booking->id)->orderBy('created_at')->desc();
	}

}