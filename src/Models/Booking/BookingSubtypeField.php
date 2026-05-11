<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\BaseModel;
use App\Models\Booking\DataObjects\BookingSubtypeFieldValue;
use App\Models\Booking\Enums\FieldType;
use App\Models\Booking\Translations\BookingSubtypeFieldTranslation;
use App\Models\WithSoftDelete;
use Lsr\Core\App;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Helpers\Tools\Strings;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * @implements TranslatableModel<BookingSubtypeFieldTranslation>
 * @use Translatable<BookingSubtypeFieldTranslation>
 */
#[PrimaryKey('id_field')]
class BookingSubtypeField extends BaseModel implements TranslatableModel
{
	use WithSoftDelete;

	/**
	 * @phpstan-use Translatable<BookingSubtypeFieldTranslation>
	 */
	use Translatable;

	public const string TABLE = 'booking_subtype_fields';

	public FieldType $type        = FieldType::TEXT;
	public string    $name        = '';
	public string    $label       = '';
	public ?string   $description = null;
	public bool      $required    = false;
	public ?string   $values      = null;
	public ?string   $default     = null;
	public bool $private = false;

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

	public function getId(): string {
		return 'sub_' . $this->getName();
	}

	public function getName(): string {
		return Strings::toSnakeCase($this->name);
	}

	/**
	 * @return void
	 * @throws TemplateDoesNotExistException
	 */
	public function render(mixed $value = null): void {
		$this->latte()->view($this->getTemplate(), $this->getParams($value));
	}

	private function latte(): Latte {
		return App::getService('templating.latte');
	}

	private function getTemplate(): string {
		return match ($this->type) {
			FieldType::TEXT   => 'components/fields/text',
			FieldType::BOOL   => 'components/fields/bool',
			FieldType::SELECT => 'components/fields/select',
			FieldType::MULTI  => 'components/fields/multi',
			FieldType::NUMBER => 'components/fields/number',
		};
	}

	/**
	 * @return array<string,mixed>
	 */
	private function getParams(mixed $value = null): array {
		$params = [
			'field' => $this,
			'value' => $value,
		];

		if ($this->type === FieldType::SELECT || $this->type === FieldType::MULTI) {
			$params['values'] = [];
			if (isset($this->values)) {
				$params['values'] = $this->getTranslatedValues();
			}
		}

		return $params;
	}

	/**
	 * @return string
	 * @throws TemplateDoesNotExistException
	 */
	public function renderToString(mixed $value = null): string {
		return $this->latte()->viewToString($this->getTemplate(), $this->getParams($value));
	}

	/**
	 * @return string[]
	 */
	public function getLabelsForValues(string ...$values): array {
		$return = [];
		foreach ($values as $value) {
			$return[] = $this->getLabelForValue($value);
		}
		return $return;
	}

	/**
	 * @return string[]
	 */
	public function getTranslatedLabelsForValues(?string $language = null, string ...$values): array {
		$return = [];
		foreach ($values as $value) {
			$return[] = $this->getTranslatedLabelForValue($value, $language);
		}
		return $return;
	}

	/**
	 * @return array<int,BookingSubtypeFieldValue>
	 */
	public function getTranslatedValues(?string $language = null): array {
		return $this->getTranslation($language)->parsedValues;
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

	public function getTranslatedLabelForValue(string $value, ?string $language = null): string {
		return $this->getTranslation($language)->getLabelForValue($value);
	}

	public function getTranslatedDefault(?string $language = null): ?string {
		return $this->getTranslation($language)->default;
	}

	#[NoDB]
	public string $translationClass {
		get => BookingSubtypeFieldTranslation::class;
	}

	public function getTranslatedLabel(?string $language = null) : string {
		return $this->getTranslation($language)->label;
	}

	public function getTranslatedDescription(?string $language = null) : ?string {
		return $this->getTranslation($language)->description;
	}
}