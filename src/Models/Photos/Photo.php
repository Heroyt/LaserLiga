<?php
declare(strict_types=1);

namespace App\Models\Photos;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Models\BaseModel;
use DateTimeInterface;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('id_photo')]
class Photo extends BaseModel
{

	public const string TABLE = 'photos';

	#[Required]
	public string  $identifier;
	public ?string $url      = null;
	public ?string $gameCode = null;
	public ?DateTimeInterface $exifTime = null;

	/** @var ModelCollection<PhotoVariation> */
	#[OneToMany(class: PhotoVariation::class)]
	public ModelCollection $variations;

	#[NoDB]
	public ?Game $game = null {
		get {
			if ($this->game === null && $this->gameCode !== null) {
				$this->game = GameFactory::getByCode($this->gameCode);
			}
			return $this->game;
		}
		set(?Game $value) {
			$this->game = $value;
			$this->gameCode = $value?->code;
		}
	}

	/**
	 * @return Photo[]
	 */
	public static function findForGame(Game $game, bool $cache = true): array {
		return self::query()->where('game_code = %s', $game->code)->get($cache);
	}

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