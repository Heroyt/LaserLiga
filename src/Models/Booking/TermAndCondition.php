<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\BaseModel;
use Lsr\ObjectValidation\Attributes\Uri;
use Lsr\Orm\Attributes\PrimaryKey;

#[PrimaryKey('id_term')]
class TermAndCondition extends BaseModel
{

	public const string TABLE = 'booking_terms_and_conditions';

	public string $label;

	#[Uri]
	public ?string $link = null;

	public bool $required = true;

}