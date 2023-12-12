<?php

namespace App\Models\Events;

use App\Models\Arena;
use App\Models\DataObjects\Image;
use DateTimeImmutable;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Model;
use OpenApi\Attributes as OA;

abstract class EventBase extends Model
{
	use EventRegistrationTrait;

	#[ManyToOne]
	#[OA\Property]
	public Arena  $arena;
	#[OA\Property]
	public string $name;

	#[OA\Property]
	public ?string $rules            = null;
	#[OA\Property]
	public ?string $prices           = null;
	#[OA\Property]
	public ?string $resultsSummary   = null;
	#[OA\Property]
	public ?string $image            = null;
	#[OA\Property]
	public ?string $shortDescription = null;
	#[OA\Property]
	public ?string $description      = null;

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
		$optimized = $image->getOptimized();
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