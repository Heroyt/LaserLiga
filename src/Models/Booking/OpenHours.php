<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\Arena;
use App\Models\BaseModel;
use App\Models\Booking\Enums\Day;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\ModelQuery;

#[PrimaryKey('id_open_hours')]
class OpenHours extends BaseModel
{

	public const string TABLE = 'booking_open_hours';

	#[ManyToOne]
	public Arena $arena;

	#[ManyToOne]
	public ?BookingType $type = null;

	public Day $day;

	public TimeInterval       $times;
	public OnCallTimeInterval $onCallTimes;

	/**
	 * @param Arena            $arena
	 * @param BookingType|null $type
	 *
	 * @return OpenHours[]
	 */
	public static function getForArenaAndType(Arena $arena, ?BookingType $type = null): array {
		return self::queryForArenaAndType($arena, $type)->get();
	}

	/**
	 * @param Arena            $arena
	 * @param BookingType|null $type
	 *
	 * @return ModelQuery<OpenHours>
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