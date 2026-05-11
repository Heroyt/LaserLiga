<?php
declare(strict_types=1);

namespace App\Request\Admin\Booking;

use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\ObjectValidation\Attributes\StringLength;

class BookingSubtypeRequest
{

	#[StringLength(max: 50)]
	public string $name = '';
	public ?string $description = null;
	public bool $slotFill = false;
	public bool $singlePlayerInput = false;
	public bool $unlockOnCall = false;
	/** @var int<1,max>|null */
	#[IntRange(min: 1)]
	public ?int $slotMax = null;
	/** @var int<1,max>|null */
	#[IntRange(min: 1)]
	public ?int $slotMin = null;
	/** @var int<1,max>|null */
	#[IntRange(min: 1)]
	public ?int $mergeSlots = null;
	public ?string $datetimeDescription = null;
	public ?string $infoDescription = null;
	public ?string $slotPreset = null;

}