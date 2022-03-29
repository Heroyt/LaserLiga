<?php

namespace App\Install;

class Install implements InstallInterface
{

	/**
	 * Install all necessary things the app needs
	 *
	 * @param bool $fresh
	 *
	 * @return bool
	 * @see Seeder
	 * @see DbInstall
	 */
	public static function install(bool $fresh = false) : bool {
		return DbInstall::install($fresh) && Seeder::install($fresh);
	}

}