<?php
declare(strict_types=1);

namespace App\Models\Photos;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Models\Arena;
use App\Models\BaseModel;
use DateTimeInterface;
use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_archive')]
class PhotoArchive extends BaseModel
{

	public const string TABLE = 'photo_archives';

	/** @var non-empty-string  */
	#[Required]
	public string $identifier;
	#[ManyToOne]
	public Arena $arena;
	public ?string $url = null;

	public ?string $gameCode = null;

	public bool $recreate = false;

	public DateTimeInterface $createdAt;
	public ?DateTimeInterface $lastDownload = null;

	/** @var int<0,max>  */
	#[IntRange(min:0)]
	public int $downloaded = 0;

	public bool $keepForever = false;

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

	public static function getForGame(Game $game) : ?PhotoArchive {
		return self::getForGameCode($game->code);
	}

	public static function getForGameCode(string $gameCode) : ?PhotoArchive {
		return self::query()->where('game_code = %s', $gameCode)->first();
	}

}