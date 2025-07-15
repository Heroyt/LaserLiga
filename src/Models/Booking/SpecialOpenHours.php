<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\Arena;
use App\Models\BaseModel;
use DateTimeInterface;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\ModelQuery;

#[PrimaryKey('id_special_hours')]
class SpecialOpenHours extends BaseModel
{

	public const string TABLE = 'booking_special_open_hours';

	#[ManyToOne]
	public Arena $arena;

	#[ManyToOne]
	public ?BookingType $type = null;

	public DateTimeInterface $date;

	public TimeInterval       $times;
	public OnCallTimeInterval $onCallTimes;

	/**
	 * @param Arena            $arena
	 * @param BookingType|null $type
	 *
	 * @return SpecialOpenHours[]
	 */
	public static function getForArenaAndType(Arena $arena, ?BookingType $type = null): array {
		return self::queryForArenaAndType($arena, $type)->get();
	}

	/**
	 * @param Arena            $arena
	 * @param BookingType|null $type
	 *
	 * @return ModelQuery<SpecialOpenHours>
	 */
	public static function queryForArenaAndType(Arena $arena, ?BookingType $type = null): ModelQuery {
		$query = self::query()->where('id_arena = %i', $arena->id);
		if ($type !== null) {
			$query->where('id_type = %i', $type->id);
		}
		else {
			$query->where('id_type IS NULL');
		}
		return $query;
	}

}