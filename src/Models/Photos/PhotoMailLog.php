<?php
declare(strict_types=1);

namespace App\Models\Photos;

use App\GameModels\Game\Game;
use App\Models\BaseModel;
use DateTimeInterface;
use Lsr\Orm\Attributes\PrimaryKey;

#[PrimaryKey('id_log')]
class PhotoMailLog extends BaseModel
{

	public const string TABLE = 'photo_mail_log';

	public DateTimeInterface $datetime;
	public string $email;
	public string $gameCode;

	/**
	 * @return PhotoMailLog[]
	 */
	public static function findForGame(Game $game, bool $cache = true): array {
		return self::query()->where('game_code = %s', $game->code)->orderBy('datetime')->get($cache);
	}

	/**
	 * @param non-empty-string[] $codes
	 * @return PhotoMailLog[]
	 */
	public static function findForGameCodes(array $codes = [], bool $cache = true): array {
		return self::query()->where('game_code IN %in', $codes)->orderBy('datetime')->get($cache);
	}

}