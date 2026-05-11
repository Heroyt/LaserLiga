<?php
declare(strict_types=1);

namespace App\Models\Booking;

use App\Models\BaseModel;
use DateTimeInterface;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_day_note')]
class DayNote extends BaseModel
{

	public const string TABLE = 'booking_day_notes';

	#[ManyToOne]
	public BookingType        $type;
	public DateTimeInterface $date;
	public string $note;

	public static function getForTypeAndDate(BookingType $type, DateTimeInterface $date, bool $cache = true): ?self {
		return self::query()
		           ->where('id_type = %i', $type->id)
		           ->where('date = %d', $date)
		           ->first($cache);
	}

	public function getCacheTags(): array {
		$tags = parent::getCacheTags();
		$tags[] = 'booking/times/'.$this->type->id.'/' . $this->date->format('Y-m-d');
		return $tags;
	}


}