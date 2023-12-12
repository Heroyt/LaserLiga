<?php

namespace App\Models;

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

	public const TABLE = 'music';

	#[Required]
	#[StringLength(1, 20)]
	#[OA\Property]
	public string $name;
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
		return str_replace(ROOT, App::getUrl(), $this->fileName);
	}

}