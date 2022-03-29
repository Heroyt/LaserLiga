<?php

namespace App\Install;

use App\Core\Auth\User;
use App\Core\DB;
use App\Exceptions\ValidationException;
use App\Models\Auth\UserType;
use Dibi\Exception;

class Seeder implements InstallInterface
{

	public const USER_TYPES = [
		[
			'id_user_type' => 1,
			'name'         => 'Admin',
			'super_admin'  => 1,
			'host'         => 0,
		],
		[
			'id_user_type' => 2,
			'name'         => 'Uživatel',
			'super_admin'  => 0,
			'host'         => 1,
		],
	];

	public const RIGHTS = [
		'edit-users'    => 'Can edit all users.',
	];

	public const TYPE_RIGHTS = [
		1 => [], // Admin has all rights
		2 => [],
	];

	/**
	 * @inheritDoc
	 */
	public static function install(bool $fresh = false) : bool {
		try {
			echo PHP_EOL.'Seeding...'.PHP_EOL.PHP_EOL;

			// Insert user types
			echo 'Inserting user types:'.PHP_EOL;
			if ($fresh) {
				DB::delete(UserType::TABLE, ['1=1']);
				DB::resetAutoIncrement(UserType::TABLE);
			}
			foreach (self::USER_TYPES as $insert) {
				echo json_encode($insert, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
				DB::insertIgnore(UserType::TABLE, $insert);
			}

			// Insert rights
			echo 'Inserting rights:'.PHP_EOL;
			if ($fresh) {
				DB::delete('rights', ['1=1']);
				DB::resetAutoIncrement('rights');
			}
			foreach (self::RIGHTS as $right => $description) {
				$insert = [
					'right'       => $right,
					'description' => $description,
				];
				echo json_encode($insert, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
				DB::insertIgnore('rights', $insert);
			}
			echo 'Inserting rights for user types:'.PHP_EOL;
			if ($fresh) {
				DB::delete('user_type_rights', ['1=1']);
				DB::resetAutoIncrement('user_type_rights');
			}
			foreach (self::TYPE_RIGHTS as $typeId => $rights) {
				foreach ($rights as $right) {
					$insert = [
						'id_user_type' => $typeId,
						'right'        => $right,
					];
					echo json_encode($insert, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
					DB::insertIgnore('user_type_rights', $insert);
				}
			}

			// Insert admin
			if ($fresh) {
				DB::delete(User::TABLE, ['1=1']);
				DB::resetAutoIncrement(User::TABLE);
			}
			if (!User::exists(1)) {
				echo 'Creating admin user...'.PHP_EOL;
				$user = new User();
				$user->name = 'admin';
				$user->email = 'admin@admin.cz';
				$user->id_user_type = 1;
				$user->setPassword('admin');
				if (!$user->save()) {
					return false;
				}
			}
			if (!User::exists(2)) {
				echo 'Creating carrier user...'.PHP_EOL;
				$user2 = new User();
				$user2->name = 'carrier';
				$user2->email = 'carrier@carrier.cz';
				$user2->id_user_type = 2;
				$user2->setPassword('carrier');
				if (!$user2->save()) {
					return false;
				}
			}
			if (!User::exists(3)) {
				echo 'Creating bus user...'.PHP_EOL;
				$user = new User();
				$user->name = 'bus';
				$user->email = 'bus@bus.cz';
				$user->id_user_type = 3;
				$user2->isParent = true;
				$user->parent = $user2;
				$user->setPassword('bus');
				if (!$user->save()) {
					return false;
				}
			}
			if (!User::exists(4)) {
				echo 'Creating general user...'.PHP_EOL;
				$user = new User();
				$user->name = 'user';
				$user->email = 'user@user.cz';
				$user->id_user_type = 4;
				$user->setPassword('user');
				if (!$user->save()) {
					return false;
				}
			}

		} catch (Exception|ValidationException $e) {
			echo "\e[0;31m".$e->getMessage()."\e[m\n";
			return false;
		}
		return true;
	}
}