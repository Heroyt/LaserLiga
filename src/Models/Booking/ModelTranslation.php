<?php
declare(strict_types=1);

namespace App\Models\Booking;

use Lsr\Orm\Model;

/**
 * @template T of Model
 */
interface ModelTranslation
{

	/**
	 * @param T  $parent
	 * @param string $language
	 * @param bool   $cache
	 *
	 * @return static|null
	 */
	public static function getForParentAndLanguage(Model $parent, string $language, bool $cache = true) : ?static;

}