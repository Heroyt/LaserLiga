<?php
declare(strict_types=1);

namespace App\Models\Booking\Translations;

use App\Models\BaseModel;
use App\Models\Booking\BookingType;
use App\Models\Booking\ModelTranslation;
use Lsr\Orm\Attributes\Hooks\AfterDelete;
use Lsr\Orm\Attributes\Hooks\AfterInsert;
use Lsr\Orm\Attributes\Hooks\AfterUpdate;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Model;

/**
 * @implements ModelTranslation<BookingType>
 */
#[PrimaryKey('id_type_translation')]
class BookingTypeTranslation extends BaseModel implements ModelTranslation
{

	public const string TABLE = 'booking_types_translations';
	/** @var array<int, array<string, static|null>> */
	private static array $cache = [];
	#[ManyToOne]
	public BookingType $type;
	public string $language;
	public string $name = '';

	public static function getForParentAndLanguage(Model $parent, string $language, bool $cache = true) : ?static {
		if (!$cache || !isset(self::$cache[$parent->id][$language])) {
			self::$cache[(int) $parent->id][$language] = self::query()->where('[id_type] = %i AND [language] = %s', $parent->id, $language)->first($cache);
		}
		return self::$cache[$parent->id][$language];
	}

	#[AfterUpdate, AfterDelete, AfterInsert]
	public function clearTypeCache(): void {
		$this->type->clearCache();
	}

}