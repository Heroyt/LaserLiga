<?php

namespace App\Install;

use App\Core\Auth\User;
use App\Core\DB;
use App\Core\Info;
use App\Models\Auth\UserType;
use Dibi\DriverException;
use Dibi\Exception;

class DbInstall implements InstallInterface
{

	public const TABLES = [
		'page_info'        => [
			'definition'    => "(
				`key` varchar(30) NOT NULL DEFAULT '',
				`value` text DEFAULT NULL,
				PRIMARY KEY (`key`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
		UserType::TABLE    => [
			'definition'    => "(
				`id_user_type` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`name` varchar(100) DEFAULT NULL,
				`super_admin` tinyint(1) NOT NULL DEFAULT '0',
				`host` tinyint(1) NOT NULL DEFAULT '0',
				PRIMARY KEY (`id_user_type`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
		User::TABLE        => [
			'definition'    => "(
				`id_user` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`id_user_type` int(11) unsigned NOT NULL,
				`id_parent` int(11) unsigned DEFAULT NULL,
				`name` varchar(20) NOT NULL DEFAULT '',
				`email` varchar(50) NOT NULL,
				`password` varchar(100) NOT NULL,
				PRIMARY KEY (`id_user`),
				KEY `id_user_type` (`id_user_type`),
				KEY `id_parent` (`id_parent`),
				CONSTRAINT `users_ibfk_1` FOREIGN KEY (`id_user_type`) REFERENCES `user_types` (`id_user_type`) ON UPDATE CASCADE,
				CONSTRAINT `users_ibfk_2` FOREIGN KEY (`id_parent`) REFERENCES `users` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
		'rights'           => [
			'definition'    => "(
				`right` varchar(20) NOT NULL DEFAULT '',
				`description` text,
				PRIMARY KEY (`right`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
		'user_type_rights' => [
			'definition'    => "(
				`id_user_type` int(11) unsigned NOT NULL,
				`right` varchar(20) NOT NULL DEFAULT '',
				PRIMARY KEY (`id_user_type`,`right`),
				KEY `right` (`right`),
				KEY `id_user_type` (`id_user_type`),
				CONSTRAINT `user_type_rights_ibfk_1` FOREIGN KEY (`id_user_type`) REFERENCES `user_types` (`id_user_type`) ON DELETE CASCADE ON UPDATE CASCADE,
				CONSTRAINT `user_type_rights_ibfk_2` FOREIGN KEY (`right`) REFERENCES `rights` (`right`) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
			'modifications' => [],
		],
	];

	/**
	 * Install all database tables
	 *
	 * @param bool $fresh
	 *
	 * @return bool
	 */
	public static function install(bool $fresh = false) : bool {
		try {
			if ($fresh) {
				foreach (array_reverse(self::TABLES) as $tableName => $definition) {
					DB::getConnection()->query("DROP TABLE IF EXISTS %n;", $tableName);
				}
			}

			foreach (self::TABLES as $tableName => $info) {
				$definition = $info['definition'];
				DB::getConnection()->query("CREATE TABLE IF NOT EXISTS %n $definition", $tableName);
			}
			if (!$fresh) {
				try {
					$currVersion = Info::get('db_version', 0.0);
				} catch (DriverException $e) {
					$currVersion = 0.0;
				}

				$maxVersion = $currVersion;
				foreach (self::TABLES as $tableName => $info) {
					foreach ($info['modifications'] as $version => $queries) {
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

}