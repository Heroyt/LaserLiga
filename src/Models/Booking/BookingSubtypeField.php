<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\BaseModel;
use App\Models\Booking\Enums\FieldType;
use App\Models\WithSoftDelete;
use Lsr\Orm\Attributes\PrimaryKey;

#[PrimaryKey('id_field')]
class BookingSubtypeField extends BaseModel
{
	use WithSoftDelete;

	public const string TABLE = 'booking_subtype_fields';

	public FieldType $type = FieldType::TEXT;
	public string $name = '';
	public string $label = '';
	public ?string $description = null;
	public bool $required = false;
	public ?string $values = null;
	public ?string $default = null;

}