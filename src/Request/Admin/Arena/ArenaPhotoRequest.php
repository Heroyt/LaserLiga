<?php
declare(strict_types=1);

namespace App\Request\Admin\Arena;

use Lsr\ObjectValidation\Attributes\Email;
use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\ObjectValidation\Attributes\StringLength;

class ArenaPhotoRequest
{

	public ?bool $photos_enabled = null;
	#[StringLength(max: 255)]
	public ?string $photos_bucket = null;
	#[Email]
	public ?string $photos_email = null;
	public ?string $photos_mail_text = null;
	#[IntRange(min: 7, max: 90)]
	public ?int $photos_unassigned_photo_ttl = null;
	#[IntRange(min: 1, max: 6)]
	public ?int $photos_assigned_photo_ttl = null;

}