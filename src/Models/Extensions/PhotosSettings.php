<?php
declare(strict_types=1);

namespace App\Models\Extensions;

use DateInterval;
use Dibi\Row;
use Lsr\Orm\Interfaces\InsertExtendInterface;
use OpenApi\Attributes as OA;

#[OA\Schema]
final class PhotosSettings implements InsertExtendInterface
{

	public function __construct(
		#[OA\Property]
		public bool    $enabled = false,
		#[OA\Property]
		public ?string $bucket = null,
		#[OA\Property]
		public ?string $email = null,
		#[OA\Property]
		public ?string $mailText = null,
		#[OA\Property(type: 'string', format: 'date-time')]
		public ?\DateInterval $unassignedPhotoTTL = null,
		#[OA\Property(type: 'string', format: 'date-time')]
		public ?\DateInterval $assignedPhotoTTL = null,
	) {
	}

	public static function parseRow(Row $row): static {
		return new self(
			(bool) ($row->photos_enabled ?? false),
			$row->photos_bucket ?? null,
			$row->photos_email ?? null,
			$row->photos_mail_text ?? null,
			$row->photos_unassigned_photo_ttl ? new \DateInterval($row->photos_unassigned_photo_ttl) : null,
			$row->photos_assigned_photo_ttl ? new \DateInterval($row->photos_assigned_photo_ttl) : null,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data): void {
		$data['photos_enabled'] = $this->enabled;
		$data['photos_bucket'] = $this->bucket;
		$data['photos_email'] = $this->email;
		$data['photos_mail_text'] = $this->mailText;
		$data['photos_unassigned_photo_ttl'] = $this->unassignedPhotoTTL !== null ? $this->getIntervalSpec($this->unassignedPhotoTTL) : null;
		$data['photos_assigned_photo_ttl'] = $this->assignedPhotoTTL !== null ? $this->getIntervalSpec($this->assignedPhotoTTL) : null;
	}

	/**
	 * @param DateInterval $interval
	 *
	 * @return non-empty-string
	 */
	private function getIntervalSpec(DateInterval $interval): string {
		$spec = 'P';
		foreach(['y', 'm', 'd', 'h', 'i', 's'] as $u) {
			if ($u === 'h') {
				$spec .= 'T';
			}
			$spec .= $interval->$u;
			$spec .= $u === 'i' ? 'M' : strtoupper($u);
		}
		return $spec;
	}
}