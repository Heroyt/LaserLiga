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
use App\Models\DataObjects\User\UserTokenRow;
use Dibi\DriverException;
use Dibi\Exception;
use JsonException;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Session;
use Lsr\Db\Connection;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Timer;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Nette\Security\Passwords;
use ReflectionException;
use RuntimeException;
use Tracy\Debugger;

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
		// Initialize app
		Timer::start('core.init.app');
		App::prettyUrl();
		App::setupDi();
		Timer::stop('core.init.app');

		// Start session
		$session = App::getService('session');
		assert($session instanceof Session, 'Invalid service from DI');
		Debugger::setSessionStorage($session);
		Debugger::enable(PRODUCTION ? Debugger::Production : Debugger::Development, LOG_DIR);

		// Setup database connection
		Timer::start('core.init.db');
		self::initDB();
		Timer::stop('core.init.db');

		if (isset($_COOKIE['rememberme'])) {
			/** @var Auth $auth */
			$auth = App::getService('auth');
			if (!$auth->loggedIn()) {
				$ex = explode(':', $_COOKIE['rememberme']);
				if (count($ex) === 2) {
					[$token, $validator] = $ex;
					$row = DB::select('user_tokens', '*')->where('[token] = %s AND [expire] > NOW()', $token)->fetchDto(UserTokenRow::class, cache: false);
					if (isset($row)) {
						$password = App::getService('passwords');
						assert($password instanceof Passwords);
						if ($password->verify($validator, $row->validator)) {
							$auth->setLoggedIn(User::get($row->id_user));
						}
					}
				}
			}
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
	public static function initDB(): void {
		if (isset($_ENV['noDb'])) {
			return;
		}
		try {
			$connection = App::getService('db.connection');
			assert($connection instanceof Connection);
			DB::init($connection);
		} catch (Exception | DriverException $e) {
			App::getInstance()->getLogger()->error(
				'Cannot connect to the database! ('.$e->getCode().') '.$e->getMessage()
			);
			throw new RuntimeException(
				'Cannot connect to the database!'.PHP_EOL.
				$e->getMessage().PHP_EOL.
				$e->getTraceAsString().PHP_EOL.
				json_encode(App::getInstance()->config->getConfig(), JSON_THROW_ON_ERROR),
				$e->getCode(),
				$e
			);
		}
	}

}
