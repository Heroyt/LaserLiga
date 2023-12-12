<?php

namespace App\Models\Auth;

use App\Models\Arena;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\OneToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;

#[PrimaryKey('id_user')]
class User extends \Lsr\Core\Auth\Models\User
{

	#[ManyToOne('', 'id_parent')]
	public ?User $parent = null;

	public int $id_user_type; // TODO: Figure out the error when this is deleted

	/** @var UserConnection[] */
	#[OneToMany(class: UserConnection::class)]
	public array $connections = [];

	#[OneToOne]
	public ?LigaPlayer $player = null;

	public ?\DateTimeInterface $createdAt = null;

	public static function getByCode(string $code) : ?static {
		return LigaPlayer::getByCode($code)?->user;
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function saveConnections() : bool {
		foreach ($this->connections as $connection) {
			if (!$connection->save()) {
				return false;
			}
		}
		return true;
	}

	public function save() : bool {
		return parent::save() && $this->saveConnections() && (!isset($this->player) || $this->player->save());
	}


	/**
	 * @return UserConnection[]
	 * @throws ValidationException
	 */
	public function getConnections() : array {
		if (empty($this->connections)) {
			$this->connections = UserConnection::getForUser($this);
		}
		return $this->connections;
	}

	public function addConnection(UserConnection $connection) : User {
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

	public static function getByEmail(string $email) : ?User {
		return static::query()->where('[email] = %s', $email)->first();
	}

	public static function existsByEmail(string $email) : bool {
		$test = DB::select(static::TABLE, 'count(*)')->where('[email] = %s', $email)->fetchSingle(cache: false);
		return $test > 0;
	}

	/**
	 * @param Arena|null $arena
	 *
	 * @return LigaPlayer
	 * @throws ValidationException
	 */
	public function createOrGetPlayer(?Arena $arena = null) : LigaPlayer {
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
}