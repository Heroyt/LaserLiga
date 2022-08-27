<?php

namespace App\Install;

use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\Auth\User;
use Dibi\Exception;
use JsonException;
use Lsr\Core\Auth\Models\User as UserParent;
use Lsr\Core\Auth\Models\UserType;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Exceptions\DirectoryCreationException;

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
		'edit-users' => 'Can edit all users.',
	];

	public const TYPE_RIGHTS = [
		1 => [], // Admin has all rights
		2 => [],
	];

	public const GAME_MODES = [
		[
			'id_mode'              => 1,
			'system'               => 'evo5',
			'name'                 => 'Team deathmach',
			'description'          => 'Classic team game.',
			'load_name'            => '1-TEAM-DEATHMACH',
			'type'                 => 'TEAM',
			'public'               => true,
			'mines'                => true,
			'part_win'             => true,
			'part_teams'           => true,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => true,
			'player_score'         => true,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => true,
			'player_players'       => true,
			'player_players_teams' => true,
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
			'team_score'           => true,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => false,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => true,
			'best_deaths_own'      => true,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		],
		[
			'id_mode'              => 2,
			'system'               => 'evo5',
			'name'                 => 'Deathmach',
			'description'          => 'Classic free for all game.',
			'load_name'            => '2-SOLO-DEATHMACH',
			'type'                 => 'SOLO',
			'public'               => true,
			'mines'                => true,
			'part_win'             => true,
			'part_teams'           => true,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => true,
			'player_score'         => true,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => true,
			'player_players'       => true,
			'player_players_teams' => true,
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
			'team_score'           => true,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => false,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => true,
			'best_deaths_own'      => true,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		],
		[
			'id_mode'              => 3,
			'system'               => 'evo5',
			'name'                 => 'CSGO',
			'description'          => 'Náročná hra o přežití se 3mi životy.',
			'load_name'            => '3-TEAM-CSGO',
			'type'                 => 'TEAM',
			'public'               => true,
			'mines'                => false,
			'part_win'             => true,
			'part_teams'           => true,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => false,
			'player_score'         => false,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => false,
			'player_players'       => true,
			'player_players_teams' => false,
			'player_kd'            => false,
			'player_favourites'    => false,
			'player_lives'         => true,
			'team_score'           => false,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => false,
			'best_score'           => false,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => false,
		],
		[
			'id_mode'              => 4,
			'system'               => 'evo5',
			'name'                 => 'Základny',
			'description'          => 'Strategická hra, kdy 2 týmy bojují proti sobě o zničení základny druhého týmu.',
			'load_name'            => '3-TEAM-Zakladny',
			'type'                 => 'TEAM',
			'public'               => true,
			'mines'                => true,
			'part_win'             => true,
			'part_teams'           => true,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => false,
			'player_score'         => false,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => false,
			'player_players'       => true,
			'player_players_teams' => true,
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
			'team_score'           => false,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => true,
			'best_score'           => false,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => true,
			'best_deaths_own'      => true,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => false,
		],
		[
			'id_mode'              => 5,
			'system'               => 'evo5',
			'name'                 => 'Barvičky',
			'description'          => 'Rychlá, šílená hra. Po pár smrtích se přebarvíš na barvu toho, kdo tě trefil.',
			'load_name'            => '3-TEAM-Barvicky',
			'type'                 => 'SOLO',
			'public'               => true,
			'mines'                => false,
			'part_win'             => true,
			'part_teams'           => false,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => false,
			'player_score'         => true,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => false,
			'player_players'       => true,
			'player_players_teams' => false,
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
			'team_score'           => false,
			'team_accuracy'        => false,
			'team_shots'           => false,
			'team_hits'            => false,
			'team_zakladny'        => false,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => false,
		],
		[
			'id_mode'              => 6,
			'system'               => 'evo5',
			'name'                 => 'T.M.A',
			'description'          => 'Klasická hra, ale tentokrát bez světel.',
			'load_name'            => '3-TEAM-TMA',
			'type'                 => 'TEAM',
			'public'               => true,
			'mines'                => true,
			'part_win'             => false,
			'part_teams'           => true,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => true,
			'player_score'         => true,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => true,
			'player_players'       => true,
			'player_players_teams' => false,
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
			'team_score'           => true,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => false,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		],
		[
			'id_mode'              => 7,
			'system'               => 'evo5',
			'name'                 => 'T.M.A - solo',
			'description'          => 'Klasická hra, ale tentokrát bez světel.',
			'load_name'            => '3-SOLO-TMA',
			'type'                 => 'SOLO',
			'public'               => true,
			'mines'                => true,
			'part_win'             => false,
			'part_teams'           => false,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => true,
			'player_score'         => true,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => true,
			'player_players'       => true,
			'player_players_teams' => false,
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
			'team_score'           => false,
			'team_accuracy'        => false,
			'team_shots'           => false,
			'team_hits'            => false,
			'team_zakladny'        => false,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		],
		[
			'id_mode'              => 8,
			'system'               => 'evo5',
			'name'                 => 'Apokalypsa',
			'description'          => 'Hra na zombíky! Vybraní hráči jsou zombie, kteří se snaží infikovat ostatní hráče.',
			'load_name'            => '3-TEAM-Apokalypsa',
			'type'                 => 'TEAM',
			'public'               => true,
			'mines'                => false,
			'part_win'             => true,
			'part_teams'           => true,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => true,
			'player_score'         => true,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => true,
			'player_players'       => true,
			'player_players_teams' => true,
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => false,
			'team_score'           => true,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => true,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		],
		[
			'id_mode'              => 9,
			'system'               => 'evo5',
			'name'                 => 'Survival',
			'description'          => 'Strategická hra s omezeným počtem životů a nábojů.',
			'load_name'            => '3-SOLO-SURVIVAL',
			'type'                 => 'SOLO',
			'public'               => true,
			'mines'                => false,
			'part_win'             => true,
			'part_teams'           => true,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => true,
			'player_score'         => true,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => true,
			'player_players'       => true,
			'player_players_teams' => true,
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => true,
			'team_score'           => true,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => true,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		],
		[
			'id_mode'              => 10,
			'system'               => 'evo5',
			'name'                 => 'Survival',
			'description'          => 'Strategická hra s omezeným počtem životů a nábojů.',
			'load_name'            => '3-TEAM-SURVIVAL',
			'type'                 => 'TEAM',
			'public'               => true,
			'mines'                => false,
			'part_win'             => true,
			'part_teams'           => true,
			'part_players'         => true,
			'part_hits'            => true,
			'part_best'            => true,
			'part_best_day'        => true,
			'player_score'         => true,
			'player_shots'         => true,
			'player_miss'          => true,
			'player_accuracy'      => true,
			'player_mines'         => true,
			'player_players'       => true,
			'player_players_teams' => true,
			'player_kd'            => true,
			'player_favourites'    => true,
			'player_lives'         => true,
			'team_score'           => true,
			'team_accuracy'        => true,
			'team_shots'           => true,
			'team_hits'            => true,
			'team_zakladny'        => true,
			'best_score'           => true,
			'best_hits'            => true,
			'best_deaths'          => true,
			'best_accuracy'        => true,
			'best_hits_own'        => false,
			'best_deaths_own'      => false,
			'best_shots'           => true,
			'best_miss'            => true,
			'best_mines'           => true,
		],
	];

	public const GAME_MODE_NAMES = [
		[
			'id_mode' => 1,
			'sysName' => '1-TEAM',
		],
		[
			'id_mode' => 2,
			'sysName' => '2-SOLO',
		],
		[
			'id_mode' => 3,
			'sysName' => 'CSGO',
		],
		[
			'id_mode' => 4,
			'sysName' => 'Zakladny',
		],
		[
			'id_mode' => 5,
			'sysName' => 'Barvi',
		],
		[
			'id_mode' => 6,
			'sysName' => 'TEAM-TMA',
		],
		[
			'id_mode' => 7,
			'sysName' => 'SOLO-TMA',
		],
		[
			'id_mode' => 8,
			'sysName' => 'Apokalypsa',
		],
		[
			'id_mode' => 9,
			'sysName' => 'SOLO-Survival',
		],
		[
			'id_mode' => 10,
			'sysName' => 'TEAM-Survival',
		],
	];


	/**
	 * @inheritDoc
	 * @throws JsonException
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
				DB::delete(UserParent::TABLE, ['1=1']);
				DB::resetAutoIncrement(UserParent::TABLE);
			}
			if (!User::exists(1)) {
				echo 'Creating admin user...'.PHP_EOL;
				$user = new User();
				$user->name = 'admin';
				$user->email = 'admin@admin.cz';
				$user->type = UserType::get(1);
				$user->setPassword('admin');
				if (!$user->save()) {
					return false;
				}
			}
			if (!User::exists(2)) {
				echo 'Creating general user...'.PHP_EOL;
				$user = new User();
				$user->name = 'user';
				$user->email = 'user@user.cz';
				$user->type = UserType::get(2);
				$user->setPassword('user');
				if (!$user->save()) {
					return false;
				}
			}

			// Game modes
			if ($fresh) {
				DB::delete(AbstractMode::TABLE, ['1=1']);
			}
			foreach (self::GAME_MODES as $insert) {
				DB::insertIgnore(AbstractMode::TABLE, $insert);
			}
			if ($fresh) {
				DB::delete(AbstractMode::TABLE.'-names', ['1=1']);
			}
			foreach (self::GAME_MODE_NAMES as $insert) {
				DB::insertIgnore(AbstractMode::TABLE.'-names', $insert);
			}

		} catch (Exception|ValidationException|ModelNotFoundException|DirectoryCreationException $e) {
			echo "\e[0;31m".$e->getMessage()."\e[m\n";
			return false;
		}
		return true;
	}
}