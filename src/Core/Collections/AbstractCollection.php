<?php

namespace App\Core\Collections;

use App\Core\AbstractModel;
use App\Core\Interfaces\CollectionInterface;
use App\Exceptions\InvalidCollectionClassException;
use InvalidArgumentException;

abstract class AbstractCollection implements CollectionInterface
{

	/** @var string Type of collection's data */
	protected string $type;
	/** @var AbstractModel[] */
	protected array $data = [];
	/** @var int|null Current offset for iterator access */
	protected ?int $currentOffset = null;

	/**
	 * Create a new collection from array of data
	 *
	 * @param AbstractModel[] $array
	 *
	 * @return CollectionInterface
	 */
	public static function fromArray(array $array) : CollectionInterface {
		$collection = new (static::class);
		$collection->add(...$array);
		return $collection;
	}

	public function getFirst() : ?AbstractModel {
		if (count($this->data) === 0) {
			return null;
		}
		return $this->data[0];
	}

	public function getLast() : ?AbstractModel {
		if (count($this->data) === 0) {
			return null;
		}
		return $this->data[count($this->data) - 1];
	}

	/**
	 * Get all collection's data as an array
	 *
	 * @return AbstractModel[]
	 */
	public function getAll() : array {
		return $this->data;
	}

	/**
	 * Reset collection's data
	 */
	public function clear() : void {
		$this->data = [];
	}


	/**
	 * Add new data to collection
	 *
	 * @details Checks value's type and uniqueness
	 *
	 * @param AbstractModel ...$values
	 *
	 * @return CollectionInterface
	 */
	public function add(AbstractModel ...$values) : CollectionInterface {
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
	 * @param AbstractModel $value
	 *
	 * @return bool
	 */
	protected function checkType(AbstractModel $value) : bool {
		if (!isset($this->type)) {
			$this->type = get_class($value);
			return true;
		}
		return is_subclass_of($value, $this->type) || get_class($value) === $this->type;
	}

	/**
	 * Checks whether the given model already exists in collection
	 *
	 * @param AbstractModel $model
	 *
	 * @return bool
	 */
	public function contains(AbstractModel $model) : bool {
		if ($this->type !== get_class($model)) {
			return false;
		}
		foreach ($this->data as $test) {
			/** @noinspection TypeUnsafeComparisonInspection */
			if ($test->id === $model->id) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add new data to collection
	 *
	 * @details Checks value's type and uniqueness
	 *
	 * @param AbstractModel $value
	 * @param int           $key
	 *
	 * @return CollectionInterface
	 */
	public function set(AbstractModel $value, int $key) : CollectionInterface {
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
	 * @return AbstractModel|null
	 */
	public function get(int $key) : ?AbstractModel {
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
	 * @param mixed $offset <p>
	 *                      The offset to retrieve.
	 *                      </p>
	 *
	 * @return mixed Can return all value types.
	 * @since 5.0.0
	 */
	public function offsetGet($offset) : mixed {
		return $this->data[$offset];
	}

	/**
	 * Offset to set
	 *
	 * @link  http://php.net/manual/en/arrayaccess.offsetset.php
	 *
	 * @param mixed $offset <p>
	 *                      The offset to assign the value to.
	 *                      </p>
	 * @param mixed $value  <p>
	 *                      The value to set.
	 *                      </p>
	 *
	 * @return void
	 * @since 5.0.0
	 */
	public function offsetSet($offset, $value) : void {
		$this->data[$offset] = $value;
	}

	/**
	 * Offset to unset
	 *
	 * @link  http://php.net/manual/en/arrayaccess.offsetunset.php
	 *
	 * @param mixed $offset <p>
	 *                      The offset to unset.
	 *                      </p>
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
	 * @return array data which can be serialized by <b>json_encode</b>,
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
	 * @return mixed Can return any type.
	 * @since 5.0.0
	 */
	public function current() : mixed {
		return current($this->data);
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
		return key($this->data);
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
		return $this->type;
	}

	/**
	 * Sort collection's data using a callback function
	 *
	 * @param callable $callback
	 *
	 * @return CollectionInterface
	 */
	public function sort(callable $callback) : CollectionInterface {
		usort($this->data, $callback);
		return $this;
	}

}