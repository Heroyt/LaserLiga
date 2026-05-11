<?php
declare(strict_types=1);

namespace App\Models\Booking\Translations;

use App\Models\BaseModel;
use App\Models\Booking\BookingSubtypeField;
use App\Models\Booking\DataObjects\BookingSubtypeFieldValue;
use App\Models\Booking\ModelTranslation;
use Lsr\Orm\Attributes\Hooks\AfterDelete;
use Lsr\Orm\Attributes\Hooks\AfterInsert;
use Lsr\Orm\Attributes\Hooks\AfterUpdate;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Model;

/**
 * @implements ModelTranslation<BookingSubtypeField>
 */
#[PrimaryKey('id_field_translation')]
class BookingSubtypeFieldTranslation extends BaseModel implements ModelTranslation
{

	public const string TABLE = 'booking_subtype_fields_translations';
	/** @var array<int, array<string, static|null>> */
	private static array $cache = [];
	#[ManyToOne]
	public BookingSubtypeField $type;
	public string $language;
	public string $label = '';
	public ?string $description = null;
	public ?string $values = null;
	public ?string $default = null;
	/** @var array<int,BookingSubtypeFieldValue> */
	#[NoDB]
	public array $parsedValues {
		get {
			if (empty($this->values)) {
				return [];
			}
			if (!isset($this->parsedValues)) {
				$this->parsedValues = [];
				/** @var array{key:int,value:string,label:string}[] $values */
				$values = json_decode($this->values, true, 512, JSON_THROW_ON_ERROR);
				foreach ($values as $value) {
					$this->parsedValues[$value['key']] = new BookingSubtypeFieldValue(...$value);
					if ($this->default === $value['value']) {
						$this->parsedValues[$value['key']]->default = true;
					}
				}
			}
			return $this->parsedValues;
		}
	}

	public static function getForParentAndLanguage(Model $parent, string $language, bool $cache = true) : ?static {
		if (!$cache || !isset(self::$cache[$parent->id][$language])) {
			self::$cache[(int) $parent->id][$language] = self::query()->where('[id_field] = %i AND [language] = %s', $parent->id, $language)->first($cache);
		}
		return self::$cache[$parent->id][$language];
	}

	#[AfterUpdate, AfterDelete, AfterInsert]
	public function clearFieldCache(): void {
		$this->type->clearCache();
	}

	public function getLabelForValue(string $value): string {
		$values = $this->parsedValues;
		foreach ($values as $test) {
			if ($test->value === $value) {
				return $test->label;
			}
		}
		return '';
	}

}