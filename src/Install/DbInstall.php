<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Install;

use App\Core\Info;
use Dibi\Exception;
use Dibi\Row;
use Lsr\Core\Exceptions\CyclicDependencyException;
use Lsr\Core\Migrations\MigrationLoader;
use Lsr\Db\DB;
use Lsr\Exceptions\FileException;
use Lsr\Orm\Model;
use Nette\Utils\AssertionException;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @version 0.3
 */
class DbInstall implements InstallInterface
{
	use InstallPrints;

	/** @var array{definition:string, modifications:array<string,string[]>}[] */
	public const array TABLES = [];

	/** @var array<class-string, string> */
	protected static array $classTables = [];

	/**
	 * Install all database tables
	 *
	 * @param  bool  $fresh
	 *
	 * @return bool
	 */
	public static function install(bool $fresh = false, ?OutputInterface $output = null) : bool {
		self::printInfo('Loading migrations', $output);
		// Load migration files
		$loader = new MigrationLoader(ROOT.'config/migrations.neon');
		try {
			$loader->load();
		} catch (CyclicDependencyException | FileException | \Nette\Neon\Exception | AssertionException $e) {
			self::printException($e, $output);
			return false;
		}

		$tables = $loader::transformToDto(array_merge($loader->migrations, self::TABLES));
		uasort($tables, static fn($a, $b) => ($a->order ?? 99) - ($b->order ?? 99));

		$connection = DB::getConnection();

		try {
			if ($fresh) {
				self::printWarning('Dropping all tables', $output);
				// Drop all tables in reverse order
				foreach (array_reverse($tables) as $tableName => $definition) {
					if (class_exists($tableName)) {
						$tableName = static::getTableNameFromClass($tableName);
						if ($tableName === null) {
							continue;
						}
					}
					$connection->query("DROP TABLE IF EXISTS %n;", $tableName);
				}
			}

			// Create tables
			self::printInfo('Creating tables', $output);
			foreach ($tables as $tableName => $info) {
				if (class_exists($tableName)) {
					$tableName = static::getTableNameFromClass($tableName);
					if ($tableName === null) {
						continue;
					}
				}
				self::printDebug('Creating table '.$tableName, $output);
				$definition = $info->definition;
				$connection->query("CREATE TABLE IF NOT EXISTS %n $definition", $tableName);
			}

			// Update tables
			if (!$fresh) {
				self::printInfo('Updating tables', $output);
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
					foreach ($info->modifications as $version => $queries) {
						// Check versions
						if ($version !== 'always') {
							if (version_compare($currTableVersion, $version) > 0) {
								// Skip if this version have already been processed
								continue;
							}
							if (version_compare($maxVersion, $version) < 0) {
								$maxVersion = $version;
							}
						}

						// Run ALTER TABLE queries for current version
						foreach ($queries as $query) {
							self::printDebug('Altering table: '.$tableName.' - '.$query, $output);
							try {
								$connection->query("ALTER TABLE %n $query;", $tableName);
							} catch (Exception $e) {
								if (
									$e->getCode() === 1060
									|| $e->getCode() === 1061
									|| $e->getCode() === 1091
									|| ($e->getCode() === 1054 && str_starts_with(strtolower($query), 'drop column'))
								) {
									// Duplicate column <-> already created
									// Or column does not exist <-> already dropped
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

			// Check indexes and foreign keys
			self::printInfo('Updating indexes', $output);
			foreach ($tables as $tableName => $info) {
				if (class_exists($tableName)) {
					$tableName = static::getTableNameFromClass($tableName);
					if ($tableName === null) {
						continue;
					}
				}

				$indexNames = ['PRIMARY'];

				// Check indexes
				foreach ($info->indexes as $index) {
					if ($index->pk || count($index->columns) < 1) {
						continue;
					}

					$indexNames[] = $index->name;

					// Check current indexes
					$indexes = $connection->query("SHOW INDEX FROM %n WHERE key_name = %s;", $tableName, $index->name)
					                      ->fetchAll();
					if (!empty($indexes)) {
						// Index already exists
						continue;
					}
					$columns = [];
					for ($i = 0, $iMax = count($index->columns); $i < $iMax; $i++) {
						$columns[] = '%n';
					}
					self::printDebug(
						'Creating '.($index->unique ? 'UNIQUE ' : '').'index on: '.$tableName.' - '.$index->name.' ('.implode(', ', $index->columns).')',
						$output
					);
					$connection->query(
						   'CREATE '.($index->unique ? 'UNIQUE ' : '').'INDEX %n ON %n ('.implode(',', $columns).');',
						   $index->name,
						   $tableName,
						...$index->columns,
					);
				}

				// Check foreign keys
				foreach ($info->foreignKeys as $foreignKey) {
					$refTable = $foreignKey->refTable;
					if (class_exists($refTable)) {
						$refTable = static::getTableNameFromClass($refTable);
						if ($refTable === null) {
							continue;
						}
					}

					$indexNames[] = $foreignKey->column;

					self::printDebug(
						'Checking foreign keys for relation '.$tableName.'.'.$foreignKey->column.'->'.$refTable.'.'.$foreignKey->refColumn,
					                 $output
					);

					// Check current foreign keys
					$fks = $connection
						->select(null, 'CONSTRAINT_NAME')
						->from('INFORMATION_SCHEMA.KEY_COLUMN_USAGE')
						->where('REFERENCED_TABLE_SCHEMA = (SELECT DATABASE())')
						->where('TABLE_NAME = %s', $tableName)
						->where('COLUMN_NAME = %s', $foreignKey->column)
						->where(
							'REFERENCED_TABLE_NAME = %s AND REFERENCED_COLUMN_NAME = %s',
							$refTable,
							$foreignKey->refColumn
						)
						->fetchPairs();
					$count = count($fks);
					if ($count === 1) {
						// FK already exists
						continue;
					}
					if ($count > 1) {
						self::printWarning(
							'Multiple foreign keys found for relation '.$tableName.'.'.$foreignKey->column.'->'.$refTable.'.'.$foreignKey->refColumn.' - '.implode(', ', $fks),
						$output
						);
						// FK already exists, but is duplicated
						array_shift($fks); // Remove first element
						// Drop any duplicate foreign key
						foreach ($fks as $fkName) {
							try {
								self::printDebug('DROPPING foreign key on: '.$tableName.' - '.$fkName, $output);
								$connection->query('ALTER TABLE %n DROP FOREIGN KEY %n;', $tableName, $fkName);
							} catch (Exception $e) {
								self::printException($e, $output);
							}
						}
						continue;
					}

					// Create new foreign key
					self::printDebug('Creating foreign key on: '.$tableName.' - '.$foreignKey->column.'->'.$refTable.'.'.$foreignKey->refColumn, $output);
					$connection->query(
						'ALTER TABLE %n ADD FOREIGN KEY (%n) REFERENCES %n (%n) ON DELETE %SQL ON UPDATE %SQL;',
						$tableName,
						$foreignKey->column,
						$refTable,
						$foreignKey->refColumn,
						$foreignKey->onDelete,
						$foreignKey->onUpdate,
					);
				}

				// DROP all undefined indexes
				self::printDebug('DROPPING indexes on '.$tableName.' other then: '.implode(', ', $indexNames),$output);
				/** @var Row[] $indexes */
				$indexes = $connection->query("SHOW INDEX FROM %n WHERE key_name NOT IN %in;", $tableName, $indexNames)
				                      ->fetchAll();
				foreach ($indexes as $row) {
					try {
						self::printDebug('DROPPING index on: '.$tableName.' - '.$row->Key_name,$output);
						$connection->query('DROP INDEX %n ON %n;', $row->Key_name, $tableName);
					} catch (Exception $e) {
						if (str_contains($e->getMessage(), 'needed in a foreign key')) {
							continue; // Ignore
						}
						self::printException($e, $output);
					}
				}
			}

			// Create views
			self::printInfo('Creating views', $output);
			foreach ($loader->views as $name => $select) {
				$connection->query(
					<<<SQL
					CREATE OR REPLACE VIEW `$name`
					       AS $select;
					SQL
				);
			}
		} catch (Exception $e) {
			self::printException($e, $output);
			return false;
		}

		self::printInfo('Database installed', $output);
		return true;
	}

	/**
	 * Get a table name for a Model class
	 *
	 * @param  class-string  $className
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
