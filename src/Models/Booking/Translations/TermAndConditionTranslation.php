<?php
declare(strict_types=1);

namespace App\Models\Booking\Translations;

use App\Models\BaseModel;
use App\Models\Booking\ModelTranslation;
use App\Models\Booking\TermAndCondition;
use Lsr\ObjectValidation\Attributes\Uri;
use Lsr\Orm\Attributes\Hooks\AfterDelete;
use Lsr\Orm\Attributes\Hooks\AfterInsert;
use Lsr\Orm\Attributes\Hooks\AfterUpdate;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Model;

/**
 * @implements ModelTranslation<TermAndCondition>
 */
#[PrimaryKey('id_term_translation')]
class TermAndConditionTranslation extends BaseModel implements ModelTranslation
{

	public const string TABLE = 'booking_terms_and_conditions_translations';
	/** @var array<int, array<string, static|null>> */
	private static array $cache = [];
	#[ManyToOne]
	public TermAndCondition $term;
	public string           $language;
	public string $label;
	#[Uri]
	public ?string $link = null;

	public static function getForParentAndLanguage(Model $parent, string $language, bool $cache = true) : ?static {
		if (!$cache || !isset(self::$cache[$parent->id][$language])) {
			self::$cache[(int) $parent->id][$language] = self::query()->where('[id_term] = %i AND [language] = %s', $parent->id, $language)->first($cache);
		}
		return self::$cache[$parent->id][$language];
	}

	#[AfterUpdate, AfterDelete, AfterInsert]
	public function clearTermCache(): void {
		$this->term->clearCache();
	}

}