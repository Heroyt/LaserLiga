<?php
/**
 * @file      DB.php
 * @brief     Database connection handling
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */


namespace App\Core;

use App\Logging\Logger;
use dibi;
use Dibi\Connection;
use Dibi\Exception;
use Dibi\Fluent;
use Dibi\Result;
use InvalidArgumentException;

/**
 * @class   DB
 * @brief   Class responsible for managing Database connection and storing common queries
 * @details Database abstraction layer for managing database connection. It uses a Dibi library to connect to the database and expands on it, adding some common queries as single methods.
 *
 * @package Core
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
class DB
{

	public const USERS = 'users';


	/**
	 * @var Connection $db Dibi Database connection
	 */
	protected static Connection $db;
	protected static Logger     $log;

	/**
	 * Initialization function
	 *
	 * @post  Database connection is created and stored in DB::db variable
	 *
	 * @throws Exception
	 *
	 * @since 1.0
	 */
	public static function init() : void {
		$config = App::getConfig();
		self::$log = new Logger(LOG_DIR, 'db');
		self::$db = new Connection([
																 'driver'   => $config['Database']['DRIVER'] ?? 'mysqli',
																 'host'     => $config['Database']['HOST'] ?? 'localhost',
																 'port'     => (int) ($config['Database']['PORT'] ?? 3306),
																 'username' => $config['Database']['USER'] ?? 'root',
																 'password' => $config['Database']['PASS'] ?? '',
																 'database' => $config['Database']['DATABASE'] ?? '',
																 'charset'  => $config['Database']['COLLATE'] ?? 'utf8mb4',
															 ]);
		self::$db->getSubstitutes()->{''} = $config['Database']['PREFIX'] ?? '';
		self::$db->onEvent[] = [self::$log, 'logDb'];
	}

	/**
	 * Connection close function
	 *
	 * @pre   Connection should be initialized
	 * @post  Connection is closed
	 *
	 * @since 1.0
	 */
	public static function close() : void {
		if (isset(self::$db)) {
			self::$db->disconnect();
		}
	}

	/**
	 * Get query update
	 *
	 * @param string     $table
	 * @param iterable   $args
	 * @param array|null $where
	 *
	 * @return Fluent|int
	 *
	 * @throws Exception
	 * @since 1.0
	 */
	public static function update(string $table, iterable $args, array $where = null) : Fluent|int {
		$q = self::$db->update($table, $args);
		if (isset($where)) {
			$q = $q->where(...$where)->execute(dibi::AFFECTED_ROWS);
		}
		return $q;
	}

	/**
	 * Insert values
	 *
	 * @param string   $table
	 * @param iterable $args
	 *
	 * @return int
	 * @throws Exception
	 *
	 * @since 1.0
	 */
	public static function insert(string $table, iterable $args) : int {
		return self::$db->insert($table, $args)->execute(dibi::AFFECTED_ROWS);
	}

	/**
	 * Get query insert
	 *
	 * @param string   $table
	 * @param iterable $args
	 *
	 * @return Fluent
	 *
	 * @since 1.0
	 */
	public static function insertGet(string $table, iterable $args) : Fluent {
		return self::$db->insert($table, $args);
	}

	/**
	 * Insert value with IGNORE flag enabled
	 *
	 * @param string   $table
	 * @param iterable $args
	 *
	 * @return int
	 * @throws Exception
	 */
	public static function insertIgnore(string $table, iterable $args) : int {
		return self::$db->insert($table, $args)->setFlag('IGNORE')->execute(dibi::AFFECTED_ROWS);
	}

	/**
	 * Insert values
	 *
	 * @param string $table
	 * @param array  $where
	 *
	 * @return int
	 * @throws Exception
	 * @since 1.0
	 */
	public static function delete(string $table, array $where = []) : int {
		return self::$db->delete($table)->where(...$where)->execute(dibi::AFFECTED_ROWS);
	}

	/**
	 * Get query insert
	 *
	 * @param string $table
	 *
	 * @return Fluent
	 *
	 * @since 1.0
	 */
	public static function deleteGet(string $table) : Fluent {
		return self::$db->delete($table);
	}

	/**
	 * Get connection class
	 *
	 * @return Connection
	 *
	 * @since 1.0
	 */
	public static function getConnection() : Connection {
		return self::$db;
	}

	/**
	 * Get last generated id of the inserted row
	 *
	 * @return int
	 * @throws Exception
	 * @since 1.0
	 */
	public static function getInsertId() : int {
		return self::$db->getInsertId();
	}

	/**
	 * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query
	 *
	 * @return int
	 * @throws Exception
	 * @since 1.0
	 */
	public static function getAffectedRows() : int {
		return self::$db->getAffectedRows();
	}

	/**
	 * Start query select
	 *
	 * @param array|string $table
	 * @param mixed        ...$args
	 *
	 * @return Fluent
	 *
	 * @throws InvalidArgumentException
	 *
	 * @since 1.0
	 */
	public static function select(array|string $table, ...$args) : Fluent {
		$query = self::$db->select(...$args);
		if (is_string($table)) {
			$query->from($table);
		}
		elseif (is_array($table)) {
			$query->from(...$table);
		}
		else {
			throw new InvalidArgumentException('Invalid `$table` argument type: '.gettype($table).'. Expected string or array');
		}
		return $query;
	}

	/**
	 * Resets autoincrement value to the first available number
	 *
	 * @param string $table
	 *
	 * @return Result
	 * @throws Exception
	 */
	public static function resetAutoIncrement(string $table) : Result {
		return self::$db->query('ALTER TABLE %n AUTO_INCREMENT = 1', $table);
	}

}
