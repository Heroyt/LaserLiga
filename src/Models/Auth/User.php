<?php

namespace App\Models\Auth;

use App\GameModels\Auth\LigaPlayer;
use App\Models\Arena;
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

	/** @var UserConnection[] */
	#[OneToMany(class: UserConnection::class)]
	public array $connections = [];

	#[OneToOne]
	public ?LigaPlayer $player = null;

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
		$this->connections[] = $connection;
		return $this;
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