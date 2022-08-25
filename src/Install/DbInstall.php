<?php

namespace App\Install;

use App\Core\Auth\User;
use App\Core\Info;
use App\GameModels\Auth\Player as AuthPlayer;
use App\GameModels\Game\Evo5\Game;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\Evo5\Team;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\Arena;
use App\Models\Auth\UserConnection;
use App\Models\Auth\UserType;
use Dibi\DriverException;
use Dibi\Exception;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\CyclicDependencyException;
use Lsr\Core\Migrations\MigrationLoader;
use Lsr\Core\Models\Model;
use Lsr\Exceptions\FileException;
use Nette\Utils\AssertionException;
use ReflectionClass;
use ReflectionException;

class DbInstall implements InstallInterface
{

	/** @var array{definition:string, modifications:array}[] */
	public const TABLES = [
		UserConnection::TABLE => [
			'definition'    => "(
				`id_connection` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`id_user` int(11) unsigned NOT NULL,
				`type` enum('rfid','laserforce') NOT NULL,
				`identifier` tinytext NOT NULL,
				PRIMARY KEY (`id_connection`),
				KEY `id_user` (`id_user`),
				CONSTRAINT `user_connected_accounts_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
		Arena::TABLE          => [
			'definition'    => "(
				`id_arena` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`name` varchar(50) NOT NULL DEFAULT '',
				`lat` double DEFAULT NULL,
				`lng` double DEFAULT NULL,
				PRIMARY KEY (`id_arena`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
		'api_keys'            => [
			'definition'    => "(
				`id_key` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`id_arena` int(11) unsigned NOT NULL,
				`key` varchar(50) NOT NULL DEFAULT '',
				`name` varchar(50) DEFAULT NULL,
				`valid` tinyint(1) NOT NULL DEFAULT 1,
				PRIMARY KEY (`id_key`),
				UNIQUE KEY `key` (`key`),
				KEY `id_arena` (`id_arena`),
				KEY `valid` (`valid`),
				CONSTRAINT `api_keys_ibfk_1` FOREIGN KEY (`id_arena`) REFERENCES `arenas` (`id_arena`) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
		AuthPlayer::TABLE     => [
			'definition'    => "(
				`id_user` int(11) unsigned NOT NULL,
				`id_arena` int(11) unsigned DEFAULT NULL,
				`code` varchar(5) NOT NULL,
				`nickname` varchar(20) NOT NULL,
				`email` varchar(50) NOT NULL,
				PRIMARY KEY (`id_user`),
				KEY `id_arena` (`id_arena`),
				CONSTRAINT `players_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE,
				CONSTRAINT `players_ibfk_2` FOREIGN KEY (`id_arena`) REFERENCES `arenas` (`id_arena`) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [
				'0.2' => [
					'ADD `email` VARCHAR(50) NOT NULL AFTER `nickname`',
				]
			],
		],
		AbstractMode::TABLE   => [
			'definition'    => "(
				`id_mode` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`system` varchar(10) DEFAULT NULL,
				`name` varchar(50) DEFAULT NULL,
				`description` text DEFAULT NULL,
				`load_name` varchar(50) DEFAULT NULL,
				`type` enum('TEAM','SOLO') NOT NULL DEFAULT 'TEAM',
				`public` tinyint(1) NOT NULL DEFAULT 0,
				`mines` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli automaticky detekovat miny nebo vůbec',
				`part_win` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli má být vložena část s tím kdo vyhrál.',
				`part_teams` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli se na výsledcích zobrazuje tabulka teamů',
				`part_players` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli se na výsledcích zobrazuje tabulka hráčů',
				`part_hits` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli se na výsledcích zobrazuje tabulka zabití',
				`part_best` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli se na výsledcích zobrazuje tabulka \"Ti nej\"',
				`part_best_day` tinyint(1) NOT NULL DEFAULT 1,
				`player_score` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli se ve výsledcích hráče zobrazí skóre.',
				`player_shots` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli se ve výsledcích hráče zobrazí výstřely.',
				`player_miss` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli se ve výsledcích hráče zobrazí výstřely mimo.',
				`player_accuracy` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli se ve výsledcích hráče zobrazí přesnost',
				`player_mines` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli se ve výsledcích hráče zobrazí miny.',
				`player_players` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli se ve výsledcích hráče zobrazí Zásahy hráčů.',
				`player_players_teams` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli se ve výsledcích hráče zobrazí zabití na vlastní a protihráče.',
				`player_kd` tinyint(1) NOT NULL DEFAULT 1,
				`player_favourites` tinyint(1) NOT NULL DEFAULT 1,
				`player_lives` tinyint(1) NOT NULL DEFAULT 0,
				`team_score` tinyint(1) NOT NULL DEFAULT 1,
				`team_accuracy` tinyint(1) NOT NULL DEFAULT 1,
				`team_shots` tinyint(1) NOT NULL DEFAULT 1,
				`team_hits` tinyint(1) NOT NULL DEFAULT 1,
				`team_zakladny` tinyint(1) NOT NULL DEFAULT 0,
				`best_score` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli zobrazovat v tabulce \"Ti nej\" hodnotu',
				`best_hits` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli zobrazovat v tabulce \"Ti nej\" hodnotu',
				`best_deaths` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli zobrazovat v tabulce \"Ti nej\" hodnotu',
				`best_accuracy` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli zobrazovat v tabulce \"Ti nej\" hodnotu',
				`best_hits_own` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli zobrazovat v tabulce \"Ti nej\" hodnotu',
				`best_deaths_own` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli zobrazovat v tabulce \"Ti nej\" hodnotu',
				`best_shots` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli zobrazovat v tabulce \"Ti nej\" hodnotu',
				`best_miss` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli zobrazovat v tabulce \"Ti nej\" hodnotu',
				`best_mines` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Jestli zobrazovat v tabulce \"Ti nej\" hodnotu',
				PRIMARY KEY (`id_mode`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Seznam a nastavení módů.';",
			'modifications' => [],
		],
		'game_modes-names'    => [
			'definition'    => "(
				`id_mode` int(11) unsigned NOT NULL,
				`sysName` varchar(20) NOT NULL,
				PRIMARY KEY (`sysName`,`id_mode`),
				KEY `Mode` (`id_mode`),
				CONSTRAINT `game_modes-names_ibfk_1` FOREIGN KEY (`id_mode`) REFERENCES `game_modes` (`id_mode`) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
		Game::TABLE           => [
			'definition'    => "(
				`id_game` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`id_mode` int(11) unsigned DEFAULT NULL,
				`id_arena` int(11) unsigned DEFAULT NULL,
				`mode_name` varchar(100) DEFAULT NULL,
				`file_time` datetime DEFAULT NULL,
				`start` datetime DEFAULT NULL,
				`end` datetime DEFAULT NULL,
				`file_number` int(11) DEFAULT NULL,
				`timing_before` int(10) unsigned DEFAULT NULL,
				`timing_game_length` int(10) unsigned DEFAULT NULL,
				`timing_after` int(10) unsigned DEFAULT NULL,
				`scoring_hit_other` int(11) DEFAULT NULL,
				`scoring_hit_own` int(11) DEFAULT NULL,
				`scoring_death_other` int(11) DEFAULT NULL,
				`scoring_death_own` int(11) DEFAULT NULL,
				`scoring_hit_pod` int(11) DEFAULT NULL,
				`scoring_shot` int(11) DEFAULT NULL,
				`scoring_power_machine_gun` int(11) DEFAULT NULL,
				`scoring_power_invisibility` int(11) DEFAULT NULL,
				`scoring_power_agent` int(11) DEFAULT NULL,
				`scoring_power_shield` int(11) DEFAULT NULL,
				`code` varchar(50) DEFAULT NULL,
				`respawn` smallint(4) unsigned DEFAULT NULL,
				`lives` int(10) unsigned DEFAULT NULL,
				`ammo` int(10) unsigned DEFAULT NULL,
				PRIMARY KEY (`id_game`),
				KEY `id_mode` (`id_mode`),
				CONSTRAINT `evo5_games_ibfk_1` FOREIGN KEY (`id_mode`) REFERENCES `game_modes` (`id_mode`) ON DELETE SET NULL ON UPDATE CASCADE,
				CONSTRAINT `evo5_games_ibfk_2` FOREIGN KEY (`id_arena`) REFERENCES `arenas` (`id_arena`) ON DELETE SET NULL ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [
				'0.1' => [
					"ADD `id_arena` INT(11)  UNSIGNED  DEFAULT NULL AFTER `id_mode`",
					"ADD FOREIGN KEY (`id_arena`) REFERENCES `".Arena::TABLE."` (`id_arena`) ON DELETE SET NULL ON UPDATE CASCADE"
				]
			],
		],
		Team::TABLE           => [
			'definition'    => "(
				`id_team` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`id_game` int(11) unsigned NOT NULL,
				`color` int(10) unsigned DEFAULT NULL,
				`score` int(11) NOT NULL DEFAULT 0,
				`position` int(10) unsigned NOT NULL DEFAULT 0,
				`name` varchar(20) DEFAULT NULL,
				PRIMARY KEY (`id_team`),
				KEY `id_game` (`id_game`),
				CONSTRAINT `evo5_teams_ibfk_1` FOREIGN KEY (`id_game`) REFERENCES `evo5_games` (`id_game`) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
		Player::TABLE         => [
			'definition' => "(
				`id_player` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`id_game` int(11) unsigned NOT NULL,
				`id_team` int(11) unsigned DEFAULT NULL,
				`name` varchar(20) NOT NULL DEFAULT '',
				`score` int(11) NOT NULL DEFAULT 0,
				`vest` int(10) unsigned NOT NULL DEFAULT 0,
				`shots` int(10) unsigned NOT NULL DEFAULT 0,
				`accuracy` int(10) unsigned NOT NULL DEFAULT 0,
				`hits` int(10) unsigned NOT NULL DEFAULT 0,
				`deaths` int(10) unsigned NOT NULL DEFAULT 0,
				`position` int(10) unsigned NOT NULL DEFAULT 0,
				`shot_points` int(11) NOT NULL DEFAULT 0,
				`score_bonus` int(11) NOT NULL DEFAULT 0,
				`score_powers` int(11) NOT NULL DEFAULT 0,
				`score_mines` int(11) NOT NULL DEFAULT 0,
				`ammo_rest` int(10) unsigned NOT NULL DEFAULT 0,
				`mines_hits` int(10) unsigned NOT NULL DEFAULT 0,
				`hits_other` int(10) unsigned NOT NULL DEFAULT 0,
				`hits_own` int(10) unsigned NOT NULL DEFAULT 0,
				`deaths_other` int(10) unsigned NOT NULL DEFAULT 0,
				`deaths_own` int(10) unsigned NOT NULL DEFAULT 0,
				`bonus_agent` int(10) unsigned NOT NULL DEFAULT 0,
				`bonus_invisibility` int(10) unsigned NOT NULL DEFAULT 0,
				`bonus_machine_gun` int(10) unsigned NOT NULL DEFAULT 0,
				`bonus_shield` int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY (`id_player`),
				KEY `id_game` (`id_game`),
				KEY `id_team` (`id_team`),
				CONSTRAINT `evo5_players_ibfk_1` FOREIGN KEY (`id_game`) REFERENCES `evo5_games` (`id_game`) ON DELETE CASCADE ON UPDATE CASCADE,
				CONSTRAINT `evo5_players_ibfk_2` FOREIGN KEY (`id_team`) REFERENCES `evo5_teams` (`id_team`) ON DELETE SET NULL ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
		'evo5_hits'           => [
			'definition'    => "(
				`id_player` int(11) unsigned NOT NULL,
				`id_target` int(11) unsigned NOT NULL,
				`count` int(10) unsigned DEFAULT NULL,
				PRIMARY KEY (`id_player`,`id_target`),
				KEY `id_target` (`id_target`),
				KEY `id_player` (`id_player`),
				CONSTRAINT `evo5_hits_ibfk_1` FOREIGN KEY (`id_player`) REFERENCES `evo5_players` (`id_player`) ON DELETE CASCADE ON UPDATE CASCADE,
				CONSTRAINT `evo5_hits_ibfk_2` FOREIGN KEY (`id_target`) REFERENCES `evo5_players` (`id_player`) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
	];
	protected static array $classTables = [];

	/**
	 * Install all database tables
	 *
	 * @param bool $fresh
	 *
	 * @return bool
	 */
	public static function install(bool $fresh = false) : bool {
		$loader = new MigrationLoader(ROOT.'config/migrations.neon');
		try {
			$loader->load();
		} catch (CyclicDependencyException|FileException|\Nette\Neon\Exception|AssertionException $e) {
			echo "\e[0;31m".$e->getMessage()."\e[m\n".$e->getTraceAsString()."\n";
			return false;
		}

		$tables = array_merge($loader->migrations, self::TABLES);

		try {
			if ($fresh) {
				foreach (array_reverse($tables) as $tableName => $definition) {
					if (class_exists($tableName)) {
						$tableName = static::getTableNameFromClass($tableName);
						if ($tableName === null) {
							continue;
						}
					}
					DB::getConnection()->query("DROP TABLE IF EXISTS %n;", $tableName);
				}
			}

			foreach ($tables as $tableName => $info) {
				if (class_exists($tableName)) {
					$tableName = static::getTableNameFromClass($tableName);
					if ($tableName === null) {
						continue;
					}
				}
				$definition = $info['definition'];
				DB::getConnection()->query("CREATE TABLE IF NOT EXISTS %n $definition", $tableName);
			}

			// Game mode view
			DB::getConnection()->query("DROP VIEW IF EXISTS `vModesNames`");
			DB::getConnection()->query("CREATE VIEW IF NOT EXISTS `vModesNames`
AS SELECT
   `a`.`id_mode` AS `id_mode`,
   `a`.`system` AS `system`,
   `a`.`name` AS `name`,
   `a`.`description` AS `description`,
   `a`.`type` AS `type`,
   `b`.`sysName` AS `sysName`
FROM (`game_modes` `a` left join `game_modes-names` `b` on(`a`.`id_mode` = `b`.`id_mode`));");

			if (!$fresh) {
				try {
					$currVersion = Info::get('db_version', 0.0);
				} catch (DriverException $e) {
					$currVersion = 0.0;
				}

				$maxVersion = $currVersion;
				foreach ($tables as $tableName => $info) {
					if (class_exists($tableName)) {
						$tableName = static::getTableNameFromClass($tableName);
						if ($tableName === null) {
							continue;
						}
					}
					foreach ($info['modifications'] ?? [] as $version => $queries) {
						$version = (float) $version;
						if ($version <= $currVersion) {
							continue;
						}
						if ($version > $maxVersion) {
							$maxVersion = $version;
						}
						foreach ($queries as $query) {
							echo 'Altering table: '.$tableName.' - '.$query.PHP_EOL;
							DB::getConnection()->query("ALTER TABLE %n $query;", $tableName);
						}
					}
				}
				try {
					Info::set('db_version', $maxVersion);
				} catch (Exception $e) {
				}
			}
		} catch (Exception $e) {
			echo "\e[0;31m".$e->getMessage()."\e[m\n".$e->getSql()."\n";
			return false;
		}
		return true;
	}

	/**
	 * @param string $className
	 *
	 * @return string|null
	 * @throws ReflectionException
	 */
	protected static function getTableNameFromClass(string $className) : ?string {
		if (isset(static::$classTables[$className])) {
			return static::$classTables[$className];
		}
		$reflection = new ReflectionClass($className);

		while ($parent = $reflection->getParentClass()) {
			if ($parent->getName() === Model::class) {
				static::$classTables[$className] = $className::TABLE;
				return $className::TABLE;
			}
			$reflection = $parent;
		}

		return null;
	}

}