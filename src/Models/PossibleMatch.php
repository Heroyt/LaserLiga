<?php

namespace App\Models;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Models\Auth\User;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Throwable;

#[PrimaryKey('id_match')]
class PossibleMatch extends Model
{

	public const TABLE = 'possible_matches';

	#[ManyToOne]
	public User $user;
	public string $code;
	public ?bool $matched = null;

	private Game $game;

	/**
	 * @param User $user
	 * @param bool $includeMatched
	 * @return PossibleMatch[]
	 * @throws ValidationException
	 */
	public static function getForUser(User $user, bool $includeMatched = false): array {
		$query = self::query()->where('[id_user] = %i', $user->id);
		if (!$includeMatched) {
			$query->where('[matched] IS NULL');
		}
		return $query->cacheTags('user/' . $user->id . '/possibleMatches')->get();
	}

	/**
	 * @return Game
	 * @throws Throwable
	 */
	public function getGame(): Game {
		$this->game ??= GameFactory::getByCode($this->code);
		return $this->game;
	}

	public function clearCache(): void {
		parent::clearCache();
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->clean([
			Cache::Tags => [
				'user/' . $this->user->id . '/possibleMatches'
			]
		]);
	}

}