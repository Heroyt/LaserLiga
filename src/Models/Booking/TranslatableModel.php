<?php
declare(strict_types=1);

namespace App\Models\Booking;

use Lsr\Core\Exceptions\InvalidLanguageException;

/**
 * @template T of ModelTranslation
 */
interface TranslatableModel
{

	/** @var class-string<T>  */
	public string $translationClass {get;}

	/**
	 * @return T|$this
	 * @throws InvalidLanguageException
	 */
	public function getTranslation(?string $language = null) : static|ModelTranslation;

}