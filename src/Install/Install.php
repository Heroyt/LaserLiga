<?php

namespace App\Install;

use Symfony\Component\Console\Output\OutputInterface;

class Install implements InstallInterface
{
	use InstallPrints;

	/**
	 * Install all necessary things the app needs
	 *
	 * @param bool $fresh
	 *
	 * @return bool
	 * @see Seeder
	 * @see DbInstall
	 */
	public static function install(bool $fresh = false, ?OutputInterface $output = null) : bool {
		self::printInfo('Starting installation', $output);
		if (DbInstall::install($fresh, $output) && Seeder::install($fresh, $output)) {
			self::printInfo('Installation successful', $output);
			return true;
		} else {
			self::printError('Installation failed', $output);
			return false;
		}
	}

}