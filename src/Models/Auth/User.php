<?php

namespace App\Models\Auth;

use App\Models\Arena;
use App\Models\Auth\Enums\ConnectionType;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Core\Auth\Models\UserType as BaseUserType;
use Lsr\Core\Models\WithCacheClear;
use Lsr\Db\DB;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\Attributes\Relations\OneToOne;
use Lsr\Orm\Exceptions\ValidationException;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('id_user')]
class User extends \Lsr\Core\Auth\Models\User
{
	use WithCacheClear;

	public const int CURRENT_PRIVACY_VERSION = 2;

	#[ManyToOne('', 'id_parent')]
	public ?User $parent = null;

	public int $id_user_type; // TODO: Figure out the error when this is deleted

	/** @var UserType */
	#[ManyToOne(class: UserType::class)]
	public BaseUserType $type;

	/** @var ModelCollection<UserConnection> */
	#[OneToMany(class: UserConnection::class)]
	public ModelCollection $connections;

	#[OneToOne, NoDB]
	public ?LigaPlayer $player = null;

	public DateTimeInterface $createdAt;

	public ?string            $emailToken     = null;
	public ?DateTimeInterface $emailTimestamp = null;
	public bool               $isConfirmed    = false;

	public ?int               $privacyVersion             = null;
	public ?int               $privacyNotificationVersion = null;
	public ?DateTimeInterface $privacyConfirmed           = null;

	#[Instantiate]
	public PersonalDetails $personalDetails;

	/** @var int[] */
	#[NoDB]
	public array $managedArenaIds {
		get {
			if (isset($this->type) && $this->type->superAdmin) {
				$this->managedArenaIds ??= array_map(static fn(Arena $arena) => $arena->id, Arena::getAll());
				return $this->managedArenaIds;
			}

			$this->managedArenaIds ??= DB::select('user_managed_arena', 'id_arena')
			                             ->where('id_user = %i', $this->id)
			                             ->fetchPairs();
			return $this->managedArenaIds;
		}
	}

	/** @var ModelCollection<Arena>  */
	#[NoDB]
	public ModelCollection $managedArenas {
		get {
			if (!isset($this->managedArenas)) {
				if (isset($this->type) && $this->type->superAdmin) {
					$arenas = Arena::getAll();
				}
				else {
					$arenas = [];
					foreach ($this->managedArenaIds as $id) {
						$arenas[] = Arena::get($id);
					}
				}
				$this->managedArenas = new ModelCollection($arenas);
			}
			return $this->managedArenas;
		}
	}

	public static function getByCode(string $code): ?static {
		/** @phpstan-ignore-next-line */
		return LigaPlayer::getByCode($code)?->user;
	}

	public static function getByEmail(string $email): ?User {
		return static::query()->where('[email] = %s', $email)->first();
	}

	public static function existsByEmail(string $email): bool {
		$test = DB::select(static::TABLE, 'count(*)')->where('[email] = %s', $email)->fetchSingle(cache: false);
		return $test > 0;
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function saveConnections(): bool {
		foreach ($this->connections as $connection) {
			if (!$connection->save()) {
				return false;
			}
		}
		return true;
	}

	public function save(): bool {
		return parent::save() && $this->saveConnections() && (!isset($this->player) || $this->player->save());
	}

	public function removeConnection(UserConnection $connection): void {
		foreach ($this->connections as $key => $test) {
			if ($connection === $test) {
				unset($this->connections[$key]);
				$connection->delete();
				return;
			}
		}
	}

	public function addConnection(UserConnection $connection): User {
		// Find duplicates
		$found = false;
		foreach ($this->getConnections() as $connectionToTest) {
			if ($connectionToTest->type === $connection->type && $connection->identifier === $connectionToTest->identifier) {
				$found = true;
				break;
			}
		}
		if (!$found) {
			$this->connections->add($connection);
		}
		return $this;
	}

	/**
	 * @return ModelCollection<UserConnection>
	 * @throws ValidationException
	 */
	public function getConnections(): ModelCollection {
		if (empty($this->connections)) {
			$this->connections = new ModelCollection(UserConnection::getForUser($this));
		}
		return $this->connections;
	}

	public function getConnectionByType(ConnectionType $type): ?UserConnection {
		foreach ($this->getConnections() as $connection) {
			if ($connection->type === $type) {
				return $connection;
			}
		}
		return null;
	}

	/**
	 * @return UserConnection[]
	 * @throws ValidationException
	 */
	public function getPublicConnections(): array {
		$connections = [];
		foreach ($this->getConnections() as $connection) {
			if (in_array($connection->type, [ConnectionType::MY_LASERMAXX, ConnectionType::LASER_FORCE], true)) {
				$connections[] = $connection;
			}
		}
		return $connections;
	}

	/**
	 * @param Arena|null $arena
	 *
	 * @return LigaPlayer
	 * @throws ValidationException
	 */
	public function createOrGetPlayer(?Arena $arena = null): LigaPlayer {
		if (!isset($this->player)) {
			$this->player = new LigaPlayer();
			$this->player->arena = $arena;
			$this->player->id = $this->id;
			$this->player->generateRandomCode();
			$this->player->nickname = $this->name;
			$this->player->user = $this;
			$this->player->email = $this->email;
			$this->player->insert();
		}
		return $this->player;
	}

	public function insert(): bool {
		$this->createdAt ??= new DateTimeImmutable();
		return parent::insert();
	}

	public function managesArena(Arena $arena): bool {
		return in_array($arena->id, $this->managedArenaIds, true);
	}

	public function shouldRevalidatePrivacyPolicy(): bool {
		return $this->privacyVersion === null || self::CURRENT_PRIVACY_VERSION > $this->privacyVersion;
	}
}