<?php

namespace App\Models\Auth;

use App\Exceptions\DuplicateRecordException;
use App\Models\Auth\Enums\ConnectionType;
use App\Models\BaseModel;
use Lsr\Db\DB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ValidationException;

#[PrimaryKey('id_connection')]
class UserConnection extends BaseModel
{

	public const string TABLE = 'user_connected_accounts';

	public ConnectionType $type;
	#[ManyToOne]
	public User           $user;
	public string|int     $identifier;

	/**
	 * Get all connections for a specific user
	 *
	 * @param User $user
	 *
	 * @return UserConnection[]
	 * @throws ValidationException
	 */
	public static function getForUser(User $user): array {
		return self::query()->where('%n = %i', $user::getPrimaryKey(), $user->id)->get();
	}

	/**
	 * Get all connections for a specific user and connection type
	 *
	 * @param User           $user
	 * @param ConnectionType $type
	 *
	 * @return UserConnection[]
	 * @throws ValidationException
	 */
	public static function getForUserAndType(User $user, ConnectionType $type): array {
		return self::query()->where('%n = %i AND [type] = %s', $user::getPrimaryKey(), $user->id, $type->value)->get();
	}

	/**
	 * Get one connection object by its identifier
	 *
	 * @param int|string     $identifier
	 * @param ConnectionType $type
	 *
	 * @return UserConnection|null
	 */
	public static function getByIdentifier(int|string $identifier, ConnectionType $type): ?UserConnection {
		return self::query()->where('[identifier] = %s AND [type] = %s', $identifier, $type->value)->first();
	}

	public function getUrl(): string {
		return match ($this->type) {
			ConnectionType::LASER_FORCE  => 'https://v2.iplaylaserforce.com/iframe.php?memberId=' . $this->identifier,
			ConnectionType::MY_LASERMAXX => (string)$this->identifier,
			default                      => '#',
		};
	}

	/**
	 * @return bool
	 * @throws DuplicateRecordException
	 * @throws ValidationException
	 */
	public function insert(): bool {
		// Check for duplicates before inserting a new one
		/** @var int|null $test */
		$test = DB::select($this::TABLE, 'id_user')->where(
			'[type] = %s AND [identifier] = %s',
			$this->type,
			$this->identifier
		)->fetchSingle();
		if (isset($test)) {
			if ($test === $this->user->id) {
				return true; // Trying to add a duplicate for the same user -> skip
			}
			// Trying to add a duplicate for a different user -> error
			throw new DuplicateRecordException(
				'Trying to add a duplicate user connection. This connection already exists for a different user.'
			);
		}
		return parent::insert();
	}

	/**
	 * @return bool
	 * @throws DuplicateRecordException
	 * @throws ValidationException
	 */
	public function update(): bool {
		// Check for duplicates before updating an existing one
		$test = DB::select($this::TABLE, '*')->where(
			'[type] = %s AND [identifier] = %s AND %n <> %i',
			$this->type,
			$this->identifier,
			$this::getPrimaryKey(),
			$this->id
		)->fetch();
		if (isset($test)) {
			// Trying to add a duplicate -> error
			throw new DuplicateRecordException(
				'Trying to add a duplicate user connection. This connection already exists for a different user.'
			);
		}
		return parent::update();
	}

}