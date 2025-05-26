<?php
declare(strict_types=1);

namespace App\Models;

use Lsr\Db\DB;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\ModelCollection;
use OpenApi\Attributes as OA;
use Stringable;

#[PrimaryKey('id_system'), OA\Schema]
class System extends BaseModel implements Stringable
{

	public const string TABLE = 'systems';

	#[OA\Property]
	public string     $name;
	#[OA\Property]
	public SystemType $type;

	/** @var ModelCollection<ArenaSystem> */
	#[OneToMany(class: ArenaSystem::class), Instantiate, JsonExclude]
	public ModelCollection $arenas;

	public static function getDefault(?Arena $arena = null, bool $cache = true): ?System {
		$activeQuery = DB::select(ArenaSystem::TABLE, 'id_system')
		                 ->where('[default] = 1');
		if ($arena !== null) {
			$activeQuery->where('id_arena = %i', $arena->id);
		}
		return self::query()->where('id_system IN %sql', $activeQuery)->first($cache);
	}

	/**
	 * @param bool $cache
	 *
	 * @return System[]
	 */
	public static function getActive(?Arena $arena = null, bool $cache = true): array {
		$activeQuery = DB::select(ArenaSystem::TABLE, 'id_system')
		                 ->where('[active] = 1');
		if ($arena !== null) {
			$activeQuery->where('id_arena = %i', $arena->id);
		}
		return self::query()->where('id_system IN %sql', $activeQuery)->get($cache);
	}

	/**
	 * @param SystemType $type
	 * @param bool       $cache
	 *
	 * @return System[]
	 */
	public static function getForType(SystemType $type, ?Arena $arena = null, bool $cache = true): array {
		$query = self::query()->where('type = %s', $type->value);
		if ($arena !== null) {
			$activeQuery = DB::select(ArenaSystem::TABLE, 'id_system')
			                 ->where('[id_arena] = %i', $arena->id);
			$query->where('id_system IN %sql', $activeQuery);
		}
		return $query->get($cache);
	}

	public function __toString(): string {
		return $this->type->value;
	}
}