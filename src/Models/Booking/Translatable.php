<?php
declare(strict_types=1);

namespace App\Models\Booking;

use Lsr\Core\App;
use Lsr\Core\Exceptions\InvalidLanguageException;

/**
 * @template T of ModelTranslation
 */
trait Translatable
{

	/** @var array<string, static|T>  */
	protected array $translations = [];

	/**
	 * @return T|$this
	 * @throws InvalidLanguageException
	 */
	public function getTranslation(?string $language = null) : static|ModelTranslation {
		$language ??= App::getInstance()->getLanguage()->id;
		if (App::getInstance()->translations->getDefaultLangId() === $language) {
			$this->translations[$language] = $this;
		}
		$this->translations[$language] = $this->translationClass::getForParentAndLanguage($this, $language) ?? $this;
		return $this->translations[$language];
	}
}