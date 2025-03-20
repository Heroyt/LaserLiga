<?php
declare(strict_types=1);

namespace App\Models\Photos;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Models\Arena;
use App\Models\BaseModel;
use DateTimeInterface;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('id_photo')]
class Photo extends BaseModel
{

	public const string TABLE = 'photos';

	#[Required]
	public string  $identifier;
	#[ManyToOne]
	public Arena $arena;
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

	#[NoDB]
	public string $type {
		get {
			return strtolower(trim(pathinfo($this->identifier, PATHINFO_EXTENSION)));
		}
	}

	#[NoDB]
	public string $mime {
		get {
			return match ($this->type) {
				'png' => 'image/png',
				'gif' => 'image/gif',
				'webp' => 'image/webp',
				default => 'image/jpeg',
			};
		}
	}

	/**
	 * @return Photo[]
	 */
	public static function findForGame(Game $game, bool $cache = true): array {
		return self::query()->where('game_code = %s', $game->code)->get($cache);
	}

	/**
	 * @param non-empty-string[] $codes
	 * @return Photo[]
	 */
	public static function findForGameCodes(array $codes = [], bool $cache = true): array {
		return self::query()->where('game_code IN %in', $codes)->get($cache);
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

	public function findWebpOriginal() : ?PhotoVariation {
		$variations = $this->variations
			->filter(static fn(PhotoVariation $variation) => $variation->type === 'webp')
			->models;
		// Order by size in descending order
		usort($variations, static fn(PhotoVariation $a, PhotoVariation $b) => $b->size - $a->size);
		return first($variations);
	}

	public function findVariation(int $size, string $type) : ?PhotoVariation {
		return $this->variations->first(
			static fn(PhotoVariation $variation) => $variation->size === $size && $variation->type === $type
		);
	}

}