<?php

namespace App\Models\Auth;

use App\Models\Arena;
use App\Models\Tournament\League\Player as LeaguePlayer;
use App\Models\Tournament\Player as TournamentPlayer;
use App\Models\Tournament\Tournament;
use DateTimeInterface;
use Lsr\Caching\Cache;
use Lsr\Core\App;
use Lsr\Db\DB;
use Lsr\LaserLiga\PlayerInterface;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToOne;
use Lsr\Orm\Exceptions\ValidationException;
use Nette\Caching\Cache as CacheParent;
use OpenApi\Attributes as OA;

/**
 * Same as the regular player, but with the addition of the arena and user parameters
 */
#[PrimaryKey('id_user')]
#[OA\Schema]
class LigaPlayer extends Player
{

	public const array CACHE_TAGS = ['liga-players'];

	#[OneToOne]
	#[OA\Property]
	public User   $user;
	#[ManyToOne]
	#[OA\Property]
	public ?Arena $arena;
	/** @var Tournament[] */
	private array $tournaments = [];
	/** @var TournamentPlayer[] */
	private array $tournamentPlayers = [];
	/** @var LeaguePlayer[] */
	private array $leaguePlayers;

	/**
	 * @throws ValidationException
	 */
	public static function validateCode(string $code, PlayerInterface $player, string $propertyPrefix = ''): void {
		if (!$player->validateUniqueCode($player->getCode())) {
			throw new ValidationException('Invalid player\'s code. Must be unique.');
		}
	}

	/**
	 * @param string $code
	 *
	 * @return bool
	 */
	public function validateUniqueCode(string $code): bool {
		// Validate and parse a player's code
		$parts = [];
		if (!self::isPlayerCode($code, $parts)) {
			$arenaId = isset($this->arena) ? $this->arena->id : 0;
		}
		else {
			$arenaId = $parts['arena'] ?? $this->arena->id ?? 0;
			$code = $parts['code'] ?? $code;
		}
		$id = DB::select($this::TABLE, $this::getPrimaryKey())->where(
			'%n = %i AND [code] = %s',
			Arena::getPrimaryKey(),
			$arenaId,
			$code
		)->fetchSingle();
		return !isset($id) || $id === $this->id;
	}

	/**
	 * Is the input string a valid player code?
	 *
	 * @param string $code Input string
	 * @param array{arena?:int,code?:non-empty-string}  $parts If matched, this will contain the matched parts (arena ID and code)
	 *
	 * @return bool
	 */
	public static function isPlayerCode(string $code, array &$parts = []) : bool {
		if (preg_match('/(\d+)-([\da-zA-Z]{5})/', $code, $matches) === 1) {
			$parts['arena'] = (int)$matches[1];
			$parts['code'] = $matches[2];
			return true;
		}
		return false;
	}

	/**
	 * Does the input string match the player code format (at least partially)?
	 *
	 * @param string $code Input string
	 * @param string $match If matched, this will contain the matched part
	 *
	 * @return bool
	 */
	public static function isPlayerCodeFormat(string $code, string &$match = ''): bool {
		if (preg_match('/^(\d+-[a-zA-Z\d]{1,5})$/', $code, $matches) === 1) {
			$match = strtoupper($matches[1]);
			return true;
		}
		return false;
	}

	public function getCode(): string {
		return (isset($this->arena) ? $this->arena->id : 0) . '-' . $this->code;
	}

	public function fetch(bool $refresh = false): void {
		parent::fetch($refresh);
		if (!isset($this->email) && isset($this->user->email)) {
			$this->email = $this->user->email;
		}
	}

	public function jsonSerialize(): array {
		return $this->getData();
	}

	/**
	 * @param bool $respectPrivacy
	 *
	 * @return array<string, mixed>
	 */
	public function getData(bool $respectPrivacy = false): array {
		$connections = [];
		try {
			foreach ($this->user->getConnections() as $connection) {
				$connections[] = ['type' => $connection->type->value, 'identifier' => $connection->identifier];
			}
		} catch (ValidationException) {
		}
		return [
			'id'          => $this->id,
			'nickname'    => $this->nickname,
			'code'        => $this->getCode(),
			'arena'       => $this->arena?->id,
			'email'       => $this->email,
			'stats'       => $this->stats,
			'connections' => $connections,
			'title'       => $this->getTitle(),
			'avatar'      => $this->getAvatar(),
			'birthday'    => !$respectPrivacy || $this->user->privacyVersion >= 1 ? $this->birthday : null,
		];
	}

	#[AfterUpdate, AfterInsert, AfterDelete]
	public function clearCache(): void {
		parent::clearCache();

		// Invalidate cached objects
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->clean([CacheParent::Tags => ['user/' . $this->id . '/games', 'user/' . $this->id . '/stats']]);
	}

	/**
	 * @return array<string, int>
	 */
	public function getTrophyCount(bool $rankableOnly = false, ?DateTimeInterface $until = null): array {
		$query = DB::select('player_trophies_count', '[name], COUNT([name]) as [count]')
		           ->where('[id_user] = %i', $this->id)
		           ->groupBy('name')
		           ->cacheTags('trophies', 'user/' . $this->id . '/trophies');
		if ($rankableOnly) {
			$query->where('[rankable] = 1')->cacheTags('trophies/rankable', 'user/' . $this->id . '/trophies/rankable');
		}
		if (isset($until)) {
			$query->where('[datetime] <= %dt', $until);
		}
		/** @phpstan-ignore-next-line */
		return $query->fetchPairs('name', 'count');
	}

	/**
	 * @return Tournament[]
	 */
	public function getTournaments(): array {
		if (empty($this->tournaments)) {
			$this->tournaments = Tournament::query()->where(
				'id_tournament IN %sql',
				DB::select(TournamentPlayer::TABLE, 'id_tournament')->where(
					'id_user = %i',
					$this->id
				)
			)->orderBy('start')->get();
		}
		return $this->tournaments;
	}

	/**
	 * @return TournamentPlayer[]
	 */
	public function getTournamentPlayers(): array {
		if (empty($this->tournamentPlayers)) {
			$this->tournamentPlayers = TournamentPlayer::query()->where('id_user = %i', $this->id)->get();
		}
		return $this->tournamentPlayers;
	}

	/**
	 * @return LeaguePlayer[]
	 * @throws ValidationException
	 */
	public function getLeaguePlayers(): array {
		if (empty($this->leaguePlayers)) {
			$this->leaguePlayers = LeaguePlayer::query()->where('id_user = %i', $this->id)->get();
		}
		return $this->leaguePlayers;
	}

}