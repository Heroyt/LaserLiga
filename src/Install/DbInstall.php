<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Install;

use App\Core\Info;
use App\GameModels\Game\Evo5\Game;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\Evo5\Team;
use App\Models\Arena;
use Dibi\Exception;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\CyclicDependencyException;
use Lsr\Core\Migrations\MigrationLoader;
use Lsr\Core\Models\Model;
use Lsr\Exceptions\FileException;
use Nette\Utils\AssertionException;
use ReflectionClass;
use ReflectionException;

/**
 * @version 0.1
 */
class DbInstall implements InstallInterface
{

	/** @var array{definition:string, modifications?:array<string,string[]>}[] */
	public const TABLES = [
		Game::TABLE   => [
			'definition'    => "(
				`id_game` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`id_mode` int(11) unsigned DEFAULT NULL,
				`id_arena` int(11) unsigned DEFAULT NULL,
				`id_music` int(11) unsigned DEFAULT NULL,
				`mode_name` varchar(100) DEFAULT NULL,
				`file_time` datetime DEFAULT NULL,
				`import_time` datetime DEFAULT NULL,
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
				CONSTRAINT `evo5_games_ibfk_2` FOREIGN KEY (`id_arena`) REFERENCES `arenas` (`id_arena`) ON DELETE SET NULL ON UPDATE CASCADE,
				CONSTRAINT `evo5_games_ibfk_2` FOREIGN KEY (`id_music`) REFERENCES `music` (`id_music`) ON DELETE SET NULL ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [
				'0.1'   => [
					"ADD `id_arena` INT(11)  UNSIGNED  DEFAULT NULL AFTER `id_mode`",
					"ADD FOREIGN KEY (`id_arena`) REFERENCES `".Arena::TABLE."` (`id_arena`) ON DELETE SET NULL ON UPDATE CASCADE"
				],
				'0.3'   => [
					"ADD `import_time` datetime DEFAULT NULL AFTER `file_time`",
				],
				'0.4.0' => [
					"ADD `id_music` int(11) unsigned DEFAULT NULL AFTER `id_arena`",
					"ADD FOREIGN KEY (`id_music`) REFERENCES `music` (`id_music`) ON DELETE SET NULL ON UPDATE CASCADE",
				],
			],
		],
		Team::TABLE   => [
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
		Player::TABLE => [
			'definition'    => "(
				`id_player` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`id_game` int(11) unsigned NOT NULL,
				`id_team` int(11) unsigned DEFAULT NULL,
				`id_user` int(11) unsigned DEFAULT NULL,
				`name` varchar(20) NOT NULL DEFAULT '',
				`score` int(11) NOT NULL DEFAULT 0,
				`skill` int(11) NOT NULL DEFAULT 0,
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
				`vip` tinyint(1) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY (`id_player`),
				KEY `id_game` (`id_game`),
				KEY `id_team` (`id_team`),
				CONSTRAINT `evo5_players_ibfk_1` FOREIGN KEY (`id_game`) REFERENCES `evo5_games` (`id_game`) ON DELETE CASCADE ON UPDATE CASCADE,
				CONSTRAINT `evo5_players_ibfk_2` FOREIGN KEY (`id_team`) REFERENCES `evo5_teams` (`id_team`) ON DELETE SET NULL ON UPDATE CASCADE,
				CONSTRAINT `evo5_players_ibfk_3` FOREIGN KEY (`id_user`) REFERENCES `players` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [
				'0.1.0' => [
					'ADD `id_user` INT(11)  UNSIGNED  NULL  DEFAULT NULL  AFTER `id_team`',
					'ADD `skill` INT(11)  NOT NULL DEFAULT 0  AFTER `score`',
					'ADD FOREIGN KEY (`id_user`) REFERENCES `players` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE'
				],
				'0.2.0' => [
					'ADD `vip` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `bonus_shield`',
				],
			],
		],
		'evo5_hits'   => [
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

	/** @var array<class-string, string> */
	protected static array $classTables = [];

	/**
	 * Install all database tables
	 *
	 * @param bool $fresh
	 *
	 * @return bool
	 */
	public static function install(bool $fresh = false) : bool {
		// Load migration files
		$loader = new MigrationLoader(ROOT.'config/migrations.neon');
		try {
			$loader->load();
		} catch (CyclicDependencyException|FileException|\Nette\Neon\Exception|AssertionException $e) {
			echo "\e[0;31m".$e->getMessage()."\e[m\n".$e->getTraceAsString()."\n";
			return false;
		}

		/** @var array{definition:string, modifications?:array<string,string[]>}[] $tables */
		$tables = array_merge($loader->migrations, self::TABLES);

		try {
			if ($fresh) {
				// Drop all tables in reverse order
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

			// Create tables
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
				/** @var array<string,string> $tableVersions */
				$tableVersions = (array) Info::get('db_version', []);

				// Update all tables if there have been any changes to the tables
				foreach ($tables as $tableName => $info) {
					if (class_exists($tableName)) {
						$tableName = static::getTableNameFromClass($tableName);
						if ($tableName === null) {
							continue;
						}
					}
					$currTableVersion = $tableVersions[$tableName] ?? '0.0';
					$maxVersion = $currTableVersion;
					foreach ($info['modifications'] ?? [] as $version => $queries) {
						// Check versions
						if (version_compare($currTableVersion, $version) > 0) {
							// Skip if this version have already been processed
							continue;
						}
						if (version_compare($maxVersion, $version) < 0) {
							$maxVersion = $version;
						}

						// Run ALTER TABLE queries for current version
						foreach ($queries as $query) {
							echo 'Altering table: '.$tableName.' - '.$query.PHP_EOL;
							try {
								DB::getConnection()->query("ALTER TABLE %n $query;", $tableName);
							} catch (Exception $e) {
								if ($e->getCode() === 1060 || $e->getCode() === 1061) {
									// Duplicate column <-> already created
									continue;
								}
								throw $e;
							}
						}
					}
					$tableVersions[$tableName] = $maxVersion;
				}

				// Update table version cache
				try {
					Info::set('db_version', $tableVersions);
				} catch (Exception) {
				}
			}
		} catch (Exception $e) {
			echo "\e[0;31m".$e->getMessage()."\e[m\n".$e->getSql()."\n";
			return false;
		}

		return true;
	}

	/**
	 * Get a table name for a Model class
	 *
	 * @param class-string $className
	 *
	 * @return string|null
	 */
	protected static function getTableNameFromClass(string $className) : ?string {
		// Check static cache
		if (isset(static::$classTables[$className])) {
			return static::$classTables[$className];
		}

		// Try to get table name from reflection
		try {
			$reflection = new ReflectionClass($className);
		} catch (ReflectionException) { // @phpstan-ignore-line
			// Class not found
			return null;
		}

		// Check if the class is instance of Model
		while ($parent = $reflection->getParentClass()) {
			if ($parent->getName() === Model::class) {
				// Cache result
				static::$classTables[$className] = $className::TABLE;
				return $className::TABLE;
			}
			$reflection = $parent;
		}

		// Class is not a Model
		return null;
	}

}