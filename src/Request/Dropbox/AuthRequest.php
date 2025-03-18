<?php
declare(strict_types=1);

namespace App\Request\Dropbox;

use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;

class AuthRequest
{

	/** @var non-empty-string  */
	#[Required, StringLength(min: 1)]
	public string $code;

	/** @var non-empty-string  */
	#[Required, StringLength(min: 32, max: 128)]
	public string $state;

}