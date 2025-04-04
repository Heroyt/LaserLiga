<?php

namespace App\Models;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Models\Auth\User;
use Lsr\Caching\Cache;
use Lsr\Core\App;
use Lsr\Orm\Attributes\Hooks\AfterDelete;
use Lsr\Orm\Attributes\Hooks\AfterInsert;
use Lsr\Orm\Attributes\Hooks\AfterUpdate;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ValidationException;

#[PrimaryKey('id_match')]
class PossibleMatch extends BaseModel
{

	public const string TABLE = 'possible_matches';

	#[ManyToOne]
	public User   $user;
	public string $code;
	public ?bool  $matched = null;

	public Game $game {
		get {
			$this->game ??= GameFactory::getByCode($this->code);
			return $this->game;
		}
	}

	/**
	 * @param User $user
	 * @param bool $includeMatched
	 *
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

	#[AfterUpdate, AfterInsert, AfterDelete]
	public function clearCache(): void {
		parent::clearCache();
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->clean([
			              Cache::Tags => [
				              'user/' . $this->user->id . '/possibleMatches',
			              ],
		              ]);
	}

}