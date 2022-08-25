<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Core\Collections;

use App\Core\Interfaces\CollectionInterface;
use App\Exceptions\InvalidCollectionClassException;
use InvalidArgumentException;
use Lsr\Core\Models\Model;

/**
 * @template T of Model
 * @implements CollectionInterface<T>
 */
abstract class AbstractCollection implements CollectionInterface
{

	/** @var string Type of collection's data */
	protected string $type;
	/** @var T[] */
	protected array $data = [];
	/** @var int|null Current offset for iterator access */
	protected ?int $currentOffset = null;

	/**
	 * Create a new collection from array of data
	 *
	 * @param T[] $array
	 *
	 * @return CollectionInterface<T>
	 */
	public static function fromArray(array $array) : CollectionInterface {
		$collection = new (static::class);
		$collection->add(...$array);
		return $collection;
	}

	/**
	 * Add new data to collection
	 *
	 * @details Checks value's type and uniqueness
	 *
	 * @param T ...$values
	 *
	 * @return CollectionInterface<T>
	 */
	public function add(Model ...$values) : CollectionInterface {
		foreach ($values as $value) {
			if (!$this->checkType($value)) {
				throw new InvalidCollectionClassException('Class '.get_class($value).' cannot be added to collection of type '.$this->type);
			}
			if (!$this->contains($value)) {
				$this->data[] = $value;
			}
		}
		return $this;
	}

	/**
	 * Checks value's type before adding to the collection
	 *
	 * @param T $value
	 *
	 * @return bool
	 */
	protected function checkType(Model $value) : bool {
		if (!isset($this->type)) {
			$this->type = get_class($value);
			return true;
		}
		return is_subclass_of($value, $this->type);
	}

	/**
	 * Checks whether the given model already exists in collection
	 *
	 * @param T $model
	 *
	 * @return bool
	 */
	public function contains(Model $model) : bool {
		foreach ($this->data as $test) {
			/** @noinspection TypeUnsafeComparisonInspection */
			if ($test == $model) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get all collection's data as an array
	 *
	 * @return T[]
	 */
	public function getAll() : array {
		return $this->data;
	}

	/**
	 * Get last object in collection
	 *
	 * @return T|null
	 */
	public function last() : ?Model {
		/** @noinspection LoopWhichDoesNotLoopInspection */
		foreach (array_reverse($this->data) as $object) {
			return $object;
		}
		return null;
	}

	/**
	 * Add new data to collection
	 *
	 * @details Checks value's type and uniqueness
	 *
	 * @param T   $value
	 * @param int $key
	 *
	 * @return CollectionInterface<T>
	 */
	public function set(Model $value, int $key) : CollectionInterface {
		if (!$this->checkType($value)) {
			throw new InvalidCollectionClassException('Class '.get_class($value).' cannot be added to collection of type '.$this->type);
		}
		if (!empty($this->data[$key])) {
			throw new InvalidArgumentException('Cannot set data for key: '.$key.' - the key is not available.');
		}
		if (!$this->contains($value)) {
			$this->data[$key] = $value;
		}
		return $this;
	}

	/**
	 * @param int $key
	 *
	 * @return T|null
	 */
	public function get(int $key) : ?Model {
		if (empty($this->data[$key])) {
			return null;
		}
		return $this->data[$key];
	}

	/**
	 * Whether a offset exists
	 *
	 * @link  http://php.net/manual/en/arrayaccess.offsetexists.php
	 *
	 * @param mixed $offset <p>
	 *                      An offset to check for.
	 *                      </p>
	 *
	 * @return boolean true on success or false on failure.
	 * </p>
	 * <p>
	 * The return value will be casted to boolean if non-boolean was returned.
	 * @since 5.0.0
	 */
	public function offsetExists($offset) : bool {
		return isset($this->data[$offset]);
	}

	/**
	 * Offset to retrieve
	 *
	 * @link  http://php.net/manual/en/arrayaccess.offsetget.php
	 *
	 * @param int $offset The offset to retrieve.
	 *
	 * @return T|null
	 * @since 5.0.0
	 */
	public function offsetGet($offset) : ?Model {
		return $this->get($offset);
	}

	/**
	 * Offset to set
	 *
	 * @link  http://php.net/manual/en/arrayaccess.offsetset.php
	 *
	 * @param int $offset The offset to assign the value to.
	 * @param T   $value  The value to set.
	 *
	 * @return void
	 * @since 5.0.0
	 */
	public function offsetSet($offset, $value) : void {
		$this->set($value, $offset);
	}

	/**
	 * Offset to unset
	 *
	 * @link  http://php.net/manual/en/arrayaccess.offsetunset.php
	 *
	 * @param int $offset The offset to unset.
	 *
	 * @return void
	 * @since 5.0.0
	 */
	public function offsetUnset($offset) : void {
		unset($this->data[$offset]);
	}

	/**
	 * Specify data which should be serialized to JSON
	 *
	 * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return T[] data which can be serialized by <b>json_encode</b>,
	 * which is a value of any type other than a resource.
	 * @since 5.4.0
	 */
	public function jsonSerialize() : array {
		return $this->data;
	}

	/**
	 * Count elements of an object
	 *
	 * @link  http://php.net/manual/en/countable.count.php
	 * @return int The custom count as an integer.
	 * </p>
	 * <p>
	 * The return value is cast to an integer.
	 * @since 5.1.0
	 */
	public function count() : int {
		return count($this->data);
	}

	/**
	 * Return the current element
	 *
	 * @link  http://php.net/manual/en/iterator.current.php
	 * @return T|null Can return any type.
	 * @since 5.0.0
	 */
	public function current() : ?Model {
		$model = current($this->data);
		return $model === false ? null : $model;
	}

	/**
	 * Move forward to next element
	 *
	 * @link  http://php.net/manual/en/iterator.next.php
	 * @return void Any returned value is ignored.
	 * @since 5.0.0
	 */
	public function next() : void {
		next($this->data);
	}

	/**
	 * Checks if current position is valid
	 *
	 * @link  http://php.net/manual/en/iterator.valid.php
	 * @return boolean The return value will be casted to boolean and then evaluated.
	 * Returns true on success or false on failure.
	 * @since 5.0.0
	 */
	public function valid() : bool {
		return isset($this->data[$this->key()]);
	}

	/**
	 * Return the key of the current element
	 *
	 * @link  http://php.net/manual/en/iterator.key.php
	 * @return int|null scalar on success, or null on failure.
	 * @since 5.0.0
	 */
	public function key() : ?int {
		/** @var int|null $key */
		$key = key($this->data);
		return $key;
	}

	/**
	 * Rewind the Iterator to the first element
	 *
	 * @link  http://php.net/manual/en/iterator.rewind.php
	 * @return void Any returned value is ignored.
	 * @since 5.0.0
	 */
	public function rewind() : void {
		reset($this->data);
	}

	/**
	 * Get collection's model type
	 *
	 * @return string
	 */
	public function getType() : string {
		$first = $this->first();
		return isset($first) ? get_class($first) : $this->type;
	}

	/**
	 * Get first object in collection
	 *
	 * @return T|null
	 */
	public function first() : ?Model {
		/** @noinspection LoopWhichDoesNotLoopInspection */
		foreach ($this->data as $object) {
			return $object;
		}
		return null;
	}

	/**
	 * Sort collection's data using a callback function
	 *
	 * @param callable $callback
	 *
	 * @return CollectionInterface<T>
	 */
	public function sort(callable $callback) : CollectionInterface {
		usort($this->data, $callback);
		return $this;
	}

}