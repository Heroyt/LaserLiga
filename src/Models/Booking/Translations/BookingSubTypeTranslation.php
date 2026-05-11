<?php
declare(strict_types=1);

namespace App\Models\Booking\Translations;

use App\Models\BaseModel;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\ModelTranslation;
use Lsr\Orm\Attributes\Hooks\AfterDelete;
use Lsr\Orm\Attributes\Hooks\AfterInsert;
use Lsr\Orm\Attributes\Hooks\AfterUpdate;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Model;

/**
 * @implements ModelTranslation<BookingSubType>
 */
#[PrimaryKey('id_subtype_translation')]
class BookingSubTypeTranslation extends BaseModel implements ModelTranslation
{

	public const string TABLE = 'booking_subtypes_translations';
	/** @var array<int, array<string, static|null>> */
	private static array $cache = [];
	#[ManyToOne]
	public BookingSubType $subtype;
	public string $language;
	public string $name = '';
	public ?string $description = null;
	public ?string $datetimeDescription = null;
	public ?string $infoDescription = null;

	public static function getForParentAndLanguage(Model $parent, string $language, bool $cache = true) : ?static {
		if (!$cache || !isset(self::$cache[$parent->id][$language])) {
			self::$cache[(int) $parent->id][$language] = self::query()->where('[id_subtype] = %i AND [language] = %s', $parent->id, $language)->first($cache);
		}
		return self::$cache[$parent->id][$language];
	}

	#[AfterUpdate, AfterDelete, AfterInsert]
	public function clearSubTypeCache(): void {
		$this->subtype->clearCache();
	}

}