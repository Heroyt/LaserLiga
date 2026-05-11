<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\BaseModel;
use App\Models\Booking\Translations\TermAndConditionTranslation;
use Lsr\ObjectValidation\Attributes\Uri;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;

/**
 * @implements TranslatableModel<TermAndConditionTranslation>
 * @use Translatable<TermAndConditionTranslation>
 */
#[PrimaryKey('id_term')]
class TermAndCondition extends BaseModel implements TranslatableModel
{

	/**
	 * @phpstan-use Translatable<TermAndConditionTranslation>
	 */
	use Translatable;

	public const string TABLE = 'booking_terms_and_conditions';

	public string $label;

	#[Uri]
	public ?string $link = null;

	public bool $required = true;

	#[NoDB]
	public string $translationClass {
		get => TermAndConditionTranslation::class;
	}

	public function getTranslatedLabel(?string $language = null): string {
		return $this->getTranslation($language)->label;
	}

	public function getTranslatedLink(?string $language = null): ?string {
		$link = $this->getTranslation($language)->link;
		return empty($link) ? $this->link : $link;
	}
}