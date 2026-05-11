<?php
declare(strict_types=1);

namespace App\Request\Admin\Booking;

use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;

class BookingTypeRequest
{

	#[StringLength(max: 50), Required]
	public string $name;
	/** @var int<1,max>  */
	#[IntRange(min:1, max: 9999)] // Unsigned small int (4)
	public int $slotLength = 30;
	/** @var int<1,max>  */
	#[IntRange(min:1, max: 9999)] // Unsigned small int (4)
	public int $slotLimit = 11;

	public bool $openable = true;
	/** @var int<0,max>  */
	#[IntRange(min:0, max: 65535)] // Unsigned small int (5)
	public int $openableMin = 0;

	public ?string $calendarId = null;

}