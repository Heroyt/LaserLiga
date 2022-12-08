<?php
/**
 * @file      Loader.php
 * @brief     Core\Loader class
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */

/**
 * @package Core
 * @brief   Core classes
 */

namespace App\Core;

use App\Models\Auth\User;
use Dibi\Exception;
use JsonException;
use Lsr\Core\App;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use ReflectionException;
use RuntimeException;

/**
 * @class   Loader
 * @brief   Loader class to prevent any unnecessary global variable
 *
 * @package Core
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
class Loader
{

	/**
	 * Initialize everything necessary
	 *
	 * @return void
	 *
	 * @throws JsonException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws DirectoryCreationException
	 * @throws ReflectionException
	 * @post    URL request is parsed
	 * @post    Database connection established
	 *
	 * @since   1.0
	 * @version 1.0
	 */
	public static function init() : void {

		if (defined('INDEX') && INDEX) {
			// Initialize config
			self::initConfig();

			// Initialize app
			App::init();
		}

		// Setup database connection
		self::initDB();

		if (defined('INDEX') && INDEX) {
			User::init();
		}

	}

	/**
	 * Initialize configuration constants
	 *
	 * @since   1.0
	 * @version 1.0
	 */
	private static function initConfig() : void {
		$config = App::getConfig();

		if ($config['General']['PRETTY_URL'] ?? false) {
			App::prettyUrl();
		}
		else {
			App::uglyUrl();
		}
	}

	/**
	 * Initialize database connection
	 *
	 * @return void
	 *
	 * @throws RuntimeException
	 * @since   1.0
	 * @version 1.0
	 */
	public static function initDB() : void {
		try {
			DB::init();
		} catch (Exception $e) {
			App::getLogger()->error('Cannot connect to the database!'.$e->getMessage());
			throw new RuntimeException('Cannot connect to the database!', $e->getCode(), $e);
		}
	}

}
