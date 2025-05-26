<?php
declare(strict_types=1);

namespace App\Models\Photos;

use App\Models\BaseModel;
use Lsr\Core\App;
use Lsr\Helpers\Tools\Strings;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_photo_variation')]
class PhotoVariation extends BaseModel
{

	public const string TABLE = 'photo_variations';

	#[ManyToOne]
	public Photo $photo;

	/** @var non-empty-string */
	public string  $identifier;
	public ?string $url = null;
	public int     $size;
	public string  $type;

	#[NoDB]
	public string $mime {
		get => match ($this->type) {
			'png'   => 'image/png',
			'gif'   => 'image/gif',
			'webp'  => 'image/webp',
			default => 'image/jpeg',
		};
	}

	#[NoDB]
	public string $proxyUrl {
		get => App::getLink([
			                    'photos',
			                    Strings::webalize($this->photo->arena->name),
								'variations',
			                    last(explode('/', $this->identifier)),
			                    'lang' => App::getInstance()->translations->getDefaultLangId()
		                    ]);
	}

	public static function findOrCreateByIdentifier(string $identifier, bool $cache = true): self {
		$photo = self::findByIdentifier($identifier, $cache);
		if ($photo === null) {
			$photo = new self();
			$photo->identifier = $identifier;
		}
		return $photo;
	}

	public static function findByIdentifier(string $identifier, bool $cache = true): ?self {
		return self::query()->where('identifier = %s', $identifier)->first(cache: $cache);
	}

}