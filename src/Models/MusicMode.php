<?php

namespace App\Models;

use App\Models\DataObjects\Image;
use Lsr\Core\App;
use Lsr\Lg\Results\Interface\Models\MusicModeInterface;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ValidationException;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_music')]
#[OA\Schema]
class MusicMode extends BaseModel implements MusicModeInterface
{

	public const string TABLE = 'music';

	#[Required]
	#[StringLength(min: 1, max: 20)]
	#[OA\Property]
	public string  $name;
	public ?string $group        = null;
	#[OA\Property]
	public int     $order        = 0;
	#[OA\Property]
	public string  $fileName     = '';
	#[ManyToOne]
	#[OA\Property]
	public ?Arena  $arena        = null;
	#[OA\Property]
	public int     $idLocal;
	#[OA\Property]
	public int     $previewStart = 0;

	#[OA\Property]
	public ?string $backgroundImage = null;
	#[OA\Property]
	public ?string $icon            = null;

	private ?Image $backgroundImageObject = null;
	private ?Image $iconObject            = null;

	/**
	 * @param Arena|null $arena Filter music for arena
	 *
	 * @return MusicMode[]
	 * @throws ValidationException
	 */
	public static function getAll(?Arena $arena = null): array {
		$q = self::query()->orderBy('order');
		if (isset($arena)) {
			$q->where('[id_arena] = %i', $arena->id);
		}
		return $q->get();
	}

	public function getMediaUrl(): string {
		return str_replace(ROOT, App::getInstance()->getBaseUrl(), $this->fileName);
	}

	public function getBackgroundImage(): ?Image {
		if (!isset($this->backgroundImageObject) && isset($this->backgroundImage)) {
			$this->backgroundImageObject = new Image($this->backgroundImage);
		}
		return $this->backgroundImageObject;
	}

	public function getIcon(): ?Image {
		if (!isset($this->iconObject) && isset($this->icon)) {
			$this->iconObject = new Image($this->icon);
		}
		return $this->iconObject;
	}
}