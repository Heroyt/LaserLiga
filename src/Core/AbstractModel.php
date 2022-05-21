<?php

namespace App\Core;

use App\Core\Interfaces\InsertExtendInterface;
use App\Exceptions\ModelNotFoundException;
use App\Exceptions\ValidationException;
use App\Logging\DirectoryCreationException;
use App\Logging\Logger;
use App\Services\Timer;
use App\Tools\Strings;
use ArrayAccess;
use BackedEnum;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Dibi\Exception;
use Dibi\Row;
use JsonSerializable;
use RuntimeException;

abstract class AbstractModel implements JsonSerializable, ArrayAccess
{

	public const TABLE       = '';
	public const PRIMARY_KEY = 'id';

	/** @var array{validators:array<string|callable>, class: string, initialize: bool}[] Model's fields definition */
	public const DEFINITION = [];

	/** @var static[][] */
	protected static array $instances = [];

	public ?int      $id  = null;
	protected ?Row   $row = null;
	protected Logger $logger;

	/**
	 * @param int|null $id    DB model ID
	 * @param Row|null $dbRow Prefetched database row
	 *
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 */
	public function __construct(?int $id = null, ?Row $dbRow = null) {
		// Initialize instance caching if not already initialized
		if (!isset(static::$instances[$this::TABLE])) {
			static::$instances[$this::TABLE] = [];
		}

		// The created object is an existing object from DB
		if (isset($id) && !empty($this::TABLE)) {
			$this->id = $id;
			$this->row = $dbRow;
			static::$instances[$this::TABLE][$this->id] = $this;
			$this->fetch();
		}

		// Initialize all empty classes which should be initialized
		foreach ($this::DEFINITION as $name => $definition) {
			if (isset($definition['class'], $definition['initialize']) && $definition['initialize'] === true && !isset($this->$name)) {
				$class = $definition['class'];
				$this->$name = new $class();
			}
		}

		$this->logger = new Logger(LOG_DIR.'models/', $this::TABLE);
	}

	/**
	 * Fetch model's data from DB
	 *
	 * @throws ModelNotFoundException
	 */
	public function fetch(bool $refresh = false) : void {
		if (!isset($this->id) || $this->id <= 0) {
			throw new RuntimeException('Id needs to be set before fetching model\'s data.');
		}

		// Refresh data from DB if necessary
		if ($refresh || !isset($this->row)) {
			$this->row = DB::select($this::TABLE, '*')->where('%n = %i', $this::PRIMARY_KEY, $this->id)->fetch();
		}

		if (!isset($this->row)) {
			throw new ModelNotFoundException(get_class($this).' model of ID '.$this->id.' was not found.');
		}

		// Parse each column in row
		foreach ($this->row as $key => $val) {
			// Primary key check
			if ($key === $this::PRIMARY_KEY) {
				$this->id = $val;
			}

			if (property_exists($this, $key)) {
				$this->setProperty($key, $val);
				continue;
			}
			// Convert DB snake_case to camelCase
			$key = Strings::toCamelCase($key);
			if (property_exists($this, $key)) {
				$this->setProperty($key, $val);
			}
		}

		// Check classes that should be initialized differently
		foreach ($this::DEFINITION as $key => $definition) {
			$className = $definition['class'] ?? '';
			if (property_exists($this, $key) && !empty($className)) {
				// Check for classes which implement the InsertExtendInterface
				$implements = class_implements($className);
				if (isset($implements[InsertExtendInterface::class])) {
					$this->$key = $className::parseRow($this->row, $this);
				}
			}
		}
	}

	/**
	 * Set a property's value from a DB value
	 *
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return void
	 */
	protected function setProperty(string $name, mixed $value) : void {
		if ($value instanceof DateInterval && isset($this::DEFINITION[$name]['class']) && $this::DEFINITION[$name]['class'] === DateTimeInterface::class) {
			// Cast DB `time` column type into a DateTime object
			$value = new DateTime($value->format('%H:%i:%s'));
		}
		else if (isset($this::DEFINITION[$name]['class']) && enum_exists($this::DEFINITION[$name]['class'])) {
			// Check for enum values
			$enumName = $this::DEFINITION[$name]['class'];
			$value = $enumName::tryFrom($value);
		}
		$this->$name = $value;
	}

	/**
	 * @param int      $id
	 * @param Row|null $row
	 *
	 * @return static
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public static function get(int $id, ?Row $row = null) : static {
		return static::$instances[static::TABLE][$id] ?? new static($id, $row);
	}

	/**
	 * Checks if a model with given ID exists in database
	 *
	 * @param int $id
	 *
	 * @return bool
	 */
	public static function exists(int $id) : bool {
		$test = DB::select(static::TABLE, 'count(*)')->where('%n = %i', static::PRIMARY_KEY, $id)->fetchSingle();
		return $test > 0;
	}

	/**
	 * Get all models
	 *
	 * @return static[]
	 */
	public static function getAll() : array {
		return static::query()->get();
	}

	public static function query() : ModelQuery {
		return new ModelQuery(static::class);
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function save() : bool {
		return isset($this->id) && self::exists($this->id) ? $this->update() : $this->insert();
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function update() : bool {
		$this->logger->info('Updating model - '.$this->id);
		try {
			$data = $this->getQueryData();
			Timer::start('model.'.$this::TABLE.'.update');
			DB::update($this::TABLE, $data, ['%n = %i', $this::PRIMARY_KEY, $this->id]);
			Timer::stop('model.'.$this::TABLE.'.update');
		} catch (Exception $e) {
			$this->logger->error('Error running update query: '.$e->getMessage());
			$this->logger->debug('Query: '.$e->getSql());
			$this->logger->debug('Trace: '.$e->getTraceAsString());
			return false;
		}
		return true;
	}

	/**
	 * Get an array of values for DB to insert/update. Values are validated.
	 *
	 * @return array
	 * @throws ValidationException
	 */
	public function getQueryData() : array {
		Timer::start('model.'.$this::TABLE.'.getData');
		$data = [];
		foreach ($this::DEFINITION as $property => $definition) {
			$validators = $definition['validators'] ?? [];
			if (!isset($this->$property) && !in_array('required', $validators, true)) {
				if (isset($definition['default'])) {
					$data[Strings::toCamelCase($property)] = $definition['default'];
				}
				continue;
			}
			if (!empty($validators)) {
				ModelValidator::validateValue($this->$property, $validators, $this);
			}
			if ($this->$property instanceof InsertExtendInterface) {
				($this->$property)->addQueryData($data);
			}
			else if ($this->$property instanceof BackedEnum) {
				$data[Strings::toSnakeCase($property)] = $this->$property->value;
			}
			else {
				$data[Strings::toSnakeCase($property)] = $this->$property;
			}
		}
		Timer::stop('model.'.$this::TABLE.'.getData');
		return $data;
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function insert() : bool {
		$this->logger->info('Inserting new model');
		try {
			$data = $this->getQueryData();
			Timer::start('model.'.$this::TABLE.'.insert');
			DB::insert($this::TABLE, $data);
			Timer::stop('model.'.$this::TABLE.'.insert');
			$this->id = DB::getInsertId();
		} catch (Exception $e) {
			$this->logger->error('Error running insert query: '.$e->getMessage());
			$this->logger->debug('Query: '.$e->getSql());
			$this->logger->debug('Trace: '.$e->getTraceAsString());
			return false;
		}
		if (empty($this->id)) {
			$this->logger->error('Insert query passed, but ID was not returned.');
			return false;
		}
		static::$instances[$this::TABLE][$this->id] = $this;
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function jsonSerialize() : array {
		$vars = get_object_vars($this);
		if (isset($vars['row'])) {
			unset($vars['row']);
		}
		return $vars;
	}

	/**
	 * @inheritdoc
	 */
	public function offsetGet($offset) : mixed {
		if ($this->offsetExists($offset)) {
			return $this->$offset;
		}
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function offsetExists($offset) : bool {
		return property_exists($this, $offset);
	}

	/**
	 * @inheritdoc
	 */
	public function offsetSet($offset, $value) : void {
		if ($this->offsetExists($offset)) {
			$this->$offset = $value;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function offsetUnset($offset) : void {
		// Do nothing
	}

	/**
	 * Delete model from DB
	 *
	 * @return bool
	 */
	public function delete() : bool {
		$this->logger->info('Delete model: '.$this::TABLE.' of ID: '.$this->id);
		try {
			DB::delete($this::TABLE, ['%n = %i', $this::PRIMARY_KEY, $this->id]);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			$this->logger->debug($e->getTraceAsString());
			return false;
		}
		return true;
	}

}