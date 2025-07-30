<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\Arena;
use App\Models\BaseModel;
use App\Models\Booking\Enums\Day;
use Lsr\Db\DB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\ModelCollection;
use Lsr\Orm\ModelQuery;

#[PrimaryKey('id_open_hours')]
class OpenHours extends BaseModel
{

	public const string TABLE = 'booking_open_hours';

	#[ManyToOne]
	public Arena $arena;

	/** @var ModelCollection<BookingType> */
	#[ManyToMany(through: 'booking_type_open_hours', class: BookingType::class)]
	public ModelCollection $types;

	public Day $day;

	public TimeInterval       $times;
	public OnCallTimeInterval $onCallTimes;

	/**
	 * @return OpenHours[]
	 */
	public static function getForArenaAndType(Arena $arena, ?BookingType $type = null, bool $cache = true): array {
		return self::queryForArenaAndType($arena, $type)->get($cache);
	}

	/**
	 * @return ModelQuery<static>
	 */
	public static function queryForArenaAndType(Arena $arena, ?BookingType $type = null): ModelQuery {
		$query = self::query()
		             ->where('id_arena = %i', $arena->id);
		if ($type !== null) {
			$query->where(
				'[id_open_hours] IN %sql',
				DB::select('[booking_type_open_hours]', '[id_open_hours]')
				  ->where('[id_type] = %i', $type->id)
			);
		}
		else {
			$query->leftJoin('[booking_type_open_hours]', 'b')
			      ->on('[b.id_open_hours] = [a.id_open_hours]')
			      ->where('[b.id_type] IS NULL');
		}
		return $query;
	}

	public function getCacheTags(): array {
		$tags = [
			'booking',
			'open_hours',
			'open_hours/' . $this->arena->id,
		];
		foreach ($this->types as $type) {
			$tags[] = 'open_hours/type/' . $type->id;
		}
		return array_merge(
			parent::getCacheTags(),
			$tags,
		);
	}

}