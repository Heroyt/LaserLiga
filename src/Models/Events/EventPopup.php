<?php

namespace App\Models\Events;

use App\Models\DataObjects\Image;
use Dibi\Row;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;
use OpenApi\Attributes as OA;

#[OA\Schema]
class EventPopup implements InsertExtendInterface
{

	private Image $imageObj;

	public function __construct(
		#[OA\Property]
		public ?string $title = null,
		#[OA\Property]
		public ?string $description = null,
		#[OA\Property]
		public ?string $image = null,
		#[OA\Property]
		public ?string $link = null,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row): ?static {
		return new self(
			$row->popup_title,
			$row->popup_description,
			$row->popup_image,
			$row->popup_link,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data): void {
		$data['popup_title'] = $this->title;
		$data['popup_description'] = $this->description;
		$data['popup_image'] = $this->image;
		$data['popup_link'] = $this->link;
	}

	public function isActive(): bool {
		return !empty($this->image) || !empty($this->title);
	}

	public function getImageObj(): ?Image {
		if (empty($this->image)) {
			return null;
		}
		$this->imageObj ??= new Image($this->image);
		return $this->imageObj;
	}
}