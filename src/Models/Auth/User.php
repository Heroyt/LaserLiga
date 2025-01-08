<?php

namespace App\Models\Auth;

use App\Models\Arena;
use App\Models\Auth\Enums\ConnectionType;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\OneToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;

#[PrimaryKey('id_user')]
class User extends \Lsr\Core\Auth\Models\User
{
	public const int CURRENT_PRIVACY_VERSION = 1;

	#[ManyToOne('', 'id_parent')]
	public ?User $parent = null;

	public int $id_user_type; // TODO: Figure out the error when this is deleted

	/** @var UserConnection[] */
	#[OneToMany(class: UserConnection::class)]
	public array $connections = [];

	#[OneToOne, NoDB]
	public ?LigaPlayer $player = null;

	public DateTimeInterface $createdAt;

	public ?string $emailToken = null;
	public ?DateTimeInterface $emailTimestamp = null;
	public bool $isConfirmed = false;

	public ?int $privacyVersion = null;
	public ?DateTimeInterface $privacyConfirmed = null;

	/** @var int[] */
	private array $managedArenaIds;

	public static function getByCode(string $code): ?static {
		/** @phpstan-ignore-next-line  */
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

	public function removeConnection(UserConnection $connection): void {
		foreach ($this->connections as $key => $test) {
			if ($connection === $test) {
				unset($this->connections[$key]);
				$connection->delete();
				return;
			}
		}
	}

	public function insert(): bool {
		$this->createdAt ??= new DateTimeImmutable();
		return parent::insert();
	}

	public function save(): bool {
		return parent::save() && $this->saveConnections() && (!isset($this->player) || $this->player->save());
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
			$this->connections[] = $connection;
		}
		return $this;
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
	public function getConnections(): array {
		if (empty($this->connections)) {
			$this->connections = UserConnection::getForUser($this);
		}
		return $this->connections;
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

	/**
	 * @return int[]
	 */
	public function getManagedArenaIds(): array {
		$this->managedArenaIds ??= DB::select('user_managed_arena', 'id_arena')
		                             ->where('id_user = %i', $this->id)
		                             ->fetchPairs();
		return $this->managedArenaIds;
	}

	public function managesArena(Arena $arena): bool {
		return in_array($arena->id, $this->getManagedArenaIds(), true);
	}
}