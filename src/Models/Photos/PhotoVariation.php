<?php
declare(strict_types=1);

namespace App\Models\Photos;

use App\Models\BaseModel;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_photo_variation')]
class PhotoVariation extends BaseModel
{

	public const string TABLE = 'photo_variations';

	#[ManyToOne]
	public Photo $photo;

	/** @var non-empty-string  */
	public string $identifier;
	public ?string $url = null;
	public int $size;
	public string $type;

	public static function findOrCreateByIdentifier(string $identifier): self {
		$photo = self::findByIdentifier($identifier);
		if ($photo === null) {
			$photo = new self();
			$photo->identifier = $identifier;
		}
		return $photo;
	}

	public static function findByIdentifier(string $identifier): ?self {
		return self::query()->where('identifier = %s', $identifier)->first();
	}

}