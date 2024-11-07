<?php

namespace App\Models;

use App\Models\DataObjects\Image;
use Lsr\Core\App;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Required;
use Lsr\Core\Models\Attributes\Validation\StringLength;
use Lsr\Core\Models\Model;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_music')]
#[OA\Schema]
class MusicMode extends Model
{

	public const string TABLE = 'music';

	#[Required]
	#[StringLength(1, 20)]
	#[OA\Property]
	public string $name;
	public ?string $group = null;
	#[OA\Property]
	public int    $order        = 0;
	#[OA\Property]
	public string $fileName     = '';
	#[ManyToOne]
	#[OA\Property]
	public ?Arena $arena        = null;
	#[OA\Property]
	public int    $idLocal;
	#[OA\Property]
	public int    $previewStart = 0;

	public ?string $backgroundImage = null;
	public ?string $icon = null;

	private ?Image $backgroundImageObject = null;
	private ?Image $iconObject = null;

	/**
	 * @param Arena|null $arena Filter music for arena
	 *
	 * @return MusicMode[]
	 * @throws ValidationException
	 */
	public static function getAll(?Arena $arena = null) : array {
		$q = self::query()->orderBy('order');
		if (isset($arena)) {
			$q->where('[id_arena] = %i', $arena->id);
		}
		return $q->get();
	}

	public function getMediaUrl() : string {
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