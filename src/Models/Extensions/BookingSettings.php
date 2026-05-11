<?php
declare(strict_types=1);

namespace App\Models\Extensions;

use Dibi\Row;
use Lsr\Orm\Interfaces\InsertExtendInterface;
use OpenApi\Attributes as OA;

#[OA\Schema]
class BookingSettings implements InsertExtendInterface
{

	public function __construct(
		public bool $enabled = false,
		public ?string $emails = null,
		public ?string $replyTo = null,
	){}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row): ?static {
		return new self(
			!empty($row->booking_enabled) && (bool)$row->booking_enabled,
			empty($row->booking_emails) ? null : $row->booking_emails,
			empty($row->booking_reply_to) ? null : $row->booking_reply_to,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data): void {
		$data['booking_enabled'] = $this->enabled;
		$data['booking_emails'] = empty($this->emails) ? null : $this->emails;
		$data['booking_reply_to'] = empty($this->replyTo) ? null : $this->replyTo;
	}
}