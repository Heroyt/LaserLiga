<?php
declare(strict_types=1);

namespace App\Models;

use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Stringable;

#[PrimaryKey('id_system')]
class System extends BaseModel implements Stringable
{

	public const string TABLE = 'systems';

	public string $name;
	#[ManyToOne]
	public Arena $arena;
	public SystemType $type;
	#[IntRange(1, 255)]
	public int $columnCount = 15;
	#[IntRange(1, 255)]
	public int $rowCount = 15;
	public bool $default = false;
	public bool $active = true;

	public static function getDefault(?Arena $arena = null,bool $cache = true) : ?System {
		$query = self::query()->where('[default] = 1');
		if ($arena !== null) {
			$query->where('id_arena = %i', $arena->id);
		}
		return $query->first($cache);
	}

	/**
	 * @param  bool  $cache
	 * @return System[]
	 */
	public static function getActive(?Arena $arena = null, bool $cache = true) : array {
		$query = self::query()->where('active = 1');
		if ($arena !== null) {
			$query->where('id_arena = %i', $arena->id);
		}
		return $query->get($cache);
	}

	/**
	 * @param  SystemType  $type
	 * @param  bool  $cache
	 * @return System[]
	 */
	public static function getForType(SystemType $type, ?Arena $arena = null, bool $cache = true) : array {
		$query = self::query()->where('type = %s', $type->value);
		if ($arena !== null) {
			$query->where('id_arena = %i', $arena->id);
		}
		return $query->get($cache);
	}

	public function __toString() : string {
		return $this->type->value;
	}
}