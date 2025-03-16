<?php

namespace App\Models\Events;

use App\Models\Arena;
use App\Models\BaseModel;
use App\Models\DataObjects\Image;
use App\Models\Tournament\EventPriceGroup;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use OpenApi\Attributes as OA;

abstract class EventBase extends BaseModel
{
	use EventRegistrationTrait;

	#[ManyToOne]
	#[OA\Property]
	public Arena            $arena;
	#[OA\Property]
	public string           $name;
	#[OA\Property, ManyToOne]
	public ?EventPriceGroup $eventPriceGroup = null;

	#[OA\Property]
	public ?string    $rules            = null;
	#[OA\Property]
	public ?string    $prices           = null;
	#[OA\Property]
	public ?string    $resultsSummary   = null;
	#[OA\Property]
	public ?string    $image            = null;
	#[OA\Property]
	public ?string    $shortDescription = null;
	#[OA\Property]
	public ?string    $description      = null;
	#[OA\Property, Instantiate]
	public EventPopup $popup;

	#[OA\Property]
	public bool $active   = true;
	#[OA\Property]
	public bool $finished = false;

	protected Image $imageObj;

	public function getImageUrl(): ?string {
		$image = $this->getImageObj();
		if (!isset($image)) {
			return null;
		}
		$optimized = $image->optimized;
		return $optimized['webp'] ?? $optimized['original'];
	}

	/**
	 * @return Image|null
	 */
	public function getImageObj(): ?Image {
		if (!isset($this->imageObj)) {
			if (!isset($this->image)) {
				return null;
			}
			$this->imageObj = new Image($this->image[0] === '/' ? ROOT . substr($this->image, 1) : $this->image);
		}
		return $this->imageObj;
	}

	public function getImageSrcSet(): ?string {
		$image = $this->getImageObj();
		if (!isset($image)) {
			return null;
		}
		return getImageSrcSet($image);
	}

}