<?php

namespace App\Models;

use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Required;
use Lsr\Core\Models\Attributes\Validation\StringLength;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_music')]
class MusicMode extends Model
{

	public const TABLE = 'music';

	#[Required]
	#[StringLength(1, 20)]
	public string $name;
	public int    $order = 0;
	#[ManyToOne]
	public ?Arena $arena = null;

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

}