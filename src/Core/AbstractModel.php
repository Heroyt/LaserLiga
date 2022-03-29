<?php

namespace App\Core;

use App\Core\Interfaces\InsertExtendInterface;
use App\Exceptions\ModelNotFoundException;
use App\Exceptions\ValidationException;
use App\Logging\DirectoryCreationException;
use App\Logging\Logger;
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

	public const DEFINITION = [

	];

	/** @var AbstractModel[][] */
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
		if (!isset(self::$instances[$this::TABLE])) {
			self::$instances[$this::TABLE] = [];
		}
		if (isset($id) && !empty($this::TABLE)) {
			$this->id = $id;
			$this->row = $dbRow;
			$this->fetch();
			self::$instances[$this::TABLE][$this->id] = $this;
		}
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
		if ($refresh || !isset($this->row)) {
			$this->row = DB::select($this::TABLE, '*')->where('%n = %i', $this::PRIMARY_KEY, $this->id)->fetch();
		}
		if (!isset($this->row)) {
			throw new ModelNotFoundException(get_class($this).' model of ID '.$this->id.' was not found.');
		}
		foreach ($this->row as $key => $val) {
			if ($key === $this::PRIMARY_KEY) {
				$this->id = $val;
			}
			if (property_exists($this, $key)) {
				$this->setProperty($key, $val);
				continue;
			}
			$key = Strings::toCamelCase($key);
			if (property_exists($this, $key)) {
				$this->setProperty($key, $val);
			}
		}
		foreach ($this::DEFINITION as $key => $definition) {
			$className = $definition['class'] ?? '';
			if (property_exists($this, $key) && !empty($className)) {
				$implements = class_implements($className);
				if (isset($implements[InsertExtendInterface::class])) {
					$this->$key = $className::parseRow($this->row, $this);
				}
			}
		}
	}

	protected function setProperty(string $name, mixed $value) : void {
		if ($value instanceof DateInterval && isset($this::DEFINITION[$name]['class']) && $this::DEFINITION[$name]['class'] === DateTimeInterface::class) {
			$value = new DateTime($value->format('%H:%i:%s'));
		}
		else if (isset($this::DEFINITION[$name]['class']) && enum_exists($this::DEFINITION[$name]['class'])) {
			$enumName = $this::DEFINITION[$name]['class'];
			$value = $enumName::tryFrom($value);
		}
		$this->$name = $value;
	}

	/**
	 * @param int $id
	 *
	 * @return AbstractModel
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public static function get(int $id) : AbstractModel {
		return self::$instances[self::TABLE][$id] ?? new static($id);
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
	 * @return AbstractModel[]
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
		return isset($this->id) ? $this->update() : $this->insert();
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function update() : bool {
		$this->logger->info('Updating model - '.$this->id);
		try {
			DB::update($this::TABLE, $this->getQueryData(), ['%n = %i', $this::PRIMARY_KEY, $this->id]);
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
				ModelValidator::validateValue($this->$property, $validators);
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
		return $data;
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function insert() : bool {
		$this->logger->info('Inserting new model');
		try {
			DB::insert($this::TABLE, $this->getQueryData());
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
		self::$instances[$this::TABLE][$this->id] = $this;
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