<?php
declare(strict_types=1);

namespace App\Models\Photos;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Models\Arena;
use App\Models\BaseModel;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Core\App;
use Lsr\Helpers\Tools\Strings;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\Orm\Attributes\Hooks\BeforeInsert;
use Lsr\Orm\Attributes\Hooks\BeforeUpdate;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('id_photo')]
class Photo extends BaseModel
{

	public const string TABLE = 'photos';

	/** @var non-empty-string */
	#[Required]
	public string             $identifier;
	#[ManyToOne]
	public Arena              $arena;
	public ?string            $url       = null;
	public ?string            $gameCode  = null;
	public bool               $inArchive = false;
	public ?DateTimeInterface $exifTime  = null;

	public DateTimeInterface $createdAt;

	public bool $keepForever = false;

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
				'png'   => 'image/png',
				'gif'   => 'image/gif',
				'webp'  => 'image/webp',
				default => 'image/jpeg',
			};
		}
	}

	#[NoDB]
	public string $proxyUrl {
		get => App::getLink([
			                    'photos',
			                    Strings::webalize($this->arena->name),
			                    last(explode('/', $this->identifier)),
		                    ]);
	}

	/**
	 * @return Photo[]
	 */
	public static function findForGame(Game $game, bool $cache = true): array {
		return self::query()->where('game_code = %s', $game->code)->get($cache);
	}

	/**
	 * @param non-empty-string[] $codes
	 *
	 * @return Photo[]
	 */
	public static function findForGameCodes(array $codes = [], bool $cache = true): array {
		return self::query()->where('game_code IN %in', $codes)->get($cache);
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

	public function findWebpOriginal(): ?PhotoVariation {
		$variations = $this->variations
			->filter(static fn(PhotoVariation $variation) => $variation->type === 'webp')
			->models;
		// Order by size in descending order
		usort($variations, static fn(PhotoVariation $a, PhotoVariation $b) => $b->size - $a->size);
		return first($variations);
	}

	public function findVariation(int $size, string $type): ?PhotoVariation {
		return $this->variations->first(
			static fn(PhotoVariation $variation) => $variation->size === $size && $variation->type === $type
		);
	}

	#[BeforeInsert, BeforeUpdate]
	public function setCreatedAt(): void {
		if (!isset($this->createdAt)) {
			$this->createdAt = new DateTimeImmutable();
		}
	}

}