<?php

namespace App\Install;

interface InstallInterface
{

	/**
	 * Install whatever the class needs
	 *
	 * @param bool $fresh
	 *
	 * @return bool Success
	 */
	public static function install(bool $fresh = false) : bool;

}