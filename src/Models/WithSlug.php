<?php
declare(strict_types=1);

namespace App\Models;

use Dibi\Exception;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Strings;
use Lsr\Orm\Attributes\Hooks\BeforeInsert;
use Lsr\Orm\Attributes\Hooks\BeforeUpdate;
use OpenApi\Attributes as OA;

trait WithSlug
{

	#[OA\Property(example: 'laser-arena-pisek')]
	public string $slug;

	/**
	 * Checks if a model with given slug exists in database
	 *
	 * @throws Exception
	 */
	public static function existsBySlug(string $slug, bool $cache = true) : bool {
		return DB::select(static::TABLE, '*')
		         ->where('[slug] = %s', $slug)
		         ->exists($cache);
	}

	#[BeforeInsert, BeforeUpdate]
	public function generateSlug(bool $regenerate = false): string {
		if (!empty($this->slug) && !$regenerate) {
			return $this->slug;
		}
		$slug = Strings::webalize($this->getSlugName());
		$counter = 0;
		// Check if the slug already exists
		do {
			$mergedSlug = $slug . ($counter > 0 ? '-' . $counter : '');
			$test = self::getBySlug($mergedSlug);
			$counter++;
		} while ($test !== null && $test->id !== $this->id);
		$this->slug = $mergedSlug;
		return $this->slug;
	}

	abstract protected function getSlugName() : string;

	public static function getBySlug(string $slug): ?static {
		return static::query()->where('[slug] = %s', $slug)->first();
	}

}